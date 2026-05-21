<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import {
    AlertCircle,
    BookOpen,
    Calendar,
    Edit,
    GraduationCap,
    Home,
    IdCard,
    Info,
    Mail,
    Phone,
    Plus,
    School,
    ShieldCheck,
    Trash2,
    Trophy,
    User,
    Users,
} from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import * as achievementsRoutes from '@/routes/achievements';
import * as trainingsRoutes from '@/routes/trainings';

type ProfileData = {
    studentNo: string;
    lastName: string;
    firstName: string;
    middlename?: string;
    middleInitial?: string;
    extName?: string;
    dateOfBirth?: string;
    placeOfBirth?: string;
    gender?: string;
    mobileNo?: string;
    email?: string;
    resAddress?: string;
    resStreet?: string;
    resBarangay?: string;
    resTownCity?: string;
    resProvince?: string;
    permAddress?: string;
    father?: string;
    mother?: string;
    guardian?: string;
    elemSchool?: string;
    hsSchool?: string;
    shsSchool?: string;
    statusRemarks?: string;
    [key: string]: any;
};

type Achievement = {
    id: number;
    title: string;
    date_received: string;
    awarder: string | null;
    description: string | null;
};

type Training = {
    id: number;
    title: string;
    date_from: string;
    date_to: string | null;
    venue: string | null;
    organizer: string | null;
};

const props = defineProps<{
    profile: {
        data: ProfileData | null;
        error: string | null;
    };
    achievements: Achievement[];
    trainings: Training[];
    lookups?: {
        tribes?: Array<{ tribeId: number; tribeName: string }>;
        civilStatuses?: Array<{ statusId: number; civilDesc: string }>;
        religions?: Array<{ religionId: number; religion: string }>;
        nationalities?: Array<{ nationalityId: number; nationality: string }>;
    };
}>();

const activeTab = ref('personal');

const tabs = [
    { id: 'personal', label: 'Personal', icon: User },
    { id: 'academic', label: 'Academic', icon: GraduationCap },
    { id: 'family', label: 'Family', icon: Users },
    { id: 'education', label: 'Education', icon: BookOpen },
    { id: 'achievements', label: 'Awards', icon: Trophy },
    { id: 'trainings', label: 'Trainings', icon: Calendar },
    { id: 'socio', label: 'Socio-Econ', icon: Info },
];

const fullName = computed(() => {
    if (!props.profile.data) return '';
    const { firstName, middleInitial, lastName, extName } = props.profile.data;
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
    if (!dateString) return '-';
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

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(value);
};

const displayValue = (value: unknown) => {
    if (value === null || value === undefined || value === '') return '-';

    return String(value);
};

const genderLabel = computed(() => {
    const gender = props.profile.data?.gender?.trim().toUpperCase();

    if (gender === 'M') return 'Male';
    if (gender === 'F') return 'Female';

    return displayValue(props.profile.data?.gender);
});

const getInitials = (name: string) => {
    if (!name) return 'S';
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .substring(0, 2);
};

const studentPictureUrl = computed(() => {
    const raw = String(props.profile.data?.studentPicture ?? '').trim();
    if (!raw) return '';
    if (raw.startsWith('data:image')) return raw;
    if (raw.startsWith('http://') || raw.startsWith('https://')) return raw;
    if (/^[A-Za-z0-9+/=\r\n]+$/.test(raw)) {
        return `data:image/jpeg;base64,${raw.replace(/\s+/g, '')}`;
    }
    return '';
});

const personalFields = computed(() => [
    { label: 'Gender', value: genderLabel.value },
    { label: 'Birth Date', value: formatDate(props.profile.data?.dateOfBirth) },
    {
        label: 'Birth Place',
        value: displayValue(props.profile.data?.placeOfBirth),
    },
    {
        label: 'Civil Status',
        value: displayValue(props.profile.data?.civilStatus ?? 'Single'),
    },
]);

const academicFields = computed(() => [
    {
        label: 'Year Level',
        value: displayValue(props.profile.data?.yearLevelId),
    },
    {
        label: 'Max Units',
        value: displayValue(props.profile.data?.maxUnitsLoad),
    },
    {
        label: 'Date Admitted',
        value: formatDate(props.profile.data?.dateAdmitted),
    },
    { label: 'Status', value: displayValue(props.profile.data?.statusRemarks) },
]);

const familyFields = computed(() => [
    { label: 'Father', value: displayValue(props.profile.data?.father) },
    { label: 'Mother', value: displayValue(props.profile.data?.mother) },
    { label: 'Guardian', value: displayValue(props.profile.data?.guardian) },
]);

const educationFields = computed(() => [
    {
        label: 'Elementary',
        value: displayValue(props.profile.data?.elemSchool),
    },
    { label: 'High School', value: displayValue(props.profile.data?.hsSchool) },
    {
        label: 'Senior High School',
        value: displayValue(props.profile.data?.shsSchool),
    },
]);

const socioFields = computed(() => [
    {
        label: 'Household Income',
        value:
            typeof props.profile.data?.householdIncome === 'number'
                ? formatCurrency(props.profile.data.householdIncome)
                : displayValue(props.profile.data?.householdIncome),
    },
    {
        label: 'Scholarship',
        value: displayValue(props.profile.data?.scholarship),
    },
    {
        label: 'Student Type',
        value: displayValue(props.profile.data?.studentType),
    },
]);

const achievementModalOpen = ref(false);
const editingAchievement = ref<Achievement | null>(null);
const achievementForm = useForm({
    title: '',
    date_received: '',
    awarder: '',
    description: '',
});

const openAchievementModal = (achievement: Achievement | null = null) => {
    editingAchievement.value = achievement;
    if (achievement) {
        achievementForm.title = achievement.title;
        achievementForm.date_received = achievement.date_received;
        achievementForm.awarder = achievement.awarder || '';
        achievementForm.description = achievement.description || '';
    } else {
        achievementForm.reset();
    }
    achievementModalOpen.value = true;
};

const submitAchievement = () => {
    if (editingAchievement.value) {
        achievementForm.patch(
            achievementsRoutes.update.url(editingAchievement.value.id),
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    achievementModalOpen.value = false;
                    achievementForm.reset();
                },
            },
        );
    } else {
        achievementForm.post(achievementsRoutes.store.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                achievementModalOpen.value = false;
                achievementForm.reset();
            },
        });
    }
};

const trainingModalOpen = ref(false);
const editingTraining = ref<Training | null>(null);
const trainingForm = useForm({
    title: '',
    date_from: '',
    date_to: '',
    venue: '',
    organizer: '',
});

const openTrainingModal = (training: Training | null = null) => {
    editingTraining.value = training;
    if (training) {
        trainingForm.title = training.title;
        trainingForm.date_from = training.date_from;
        trainingForm.date_to = training.date_to || '';
        trainingForm.venue = training.venue || '';
        trainingForm.organizer = training.organizer || '';
    } else {
        trainingForm.reset();
    }
    trainingModalOpen.value = true;
};

const submitTraining = () => {
    if (editingTraining.value) {
        trainingForm.patch(
            trainingsRoutes.update.url(editingTraining.value.id),
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    trainingModalOpen.value = false;
                    trainingForm.reset();
                },
            },
        );
    } else {
        trainingForm.post(trainingsRoutes.store.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                trainingModalOpen.value = false;
                trainingForm.reset();
            },
        });
    }
};

const deleteConfirmOpen = ref(false);
const itemToDelete = ref<{
    id: number;
    type: 'achievement' | 'training';
    title: string;
} | null>(null);

const confirmDelete = (
    id: number,
    type: 'achievement' | 'training',
    title: string,
) => {
    itemToDelete.value = { id, type, title };
    deleteConfirmOpen.value = true;
};

const executeDelete = () => {
    if (!itemToDelete.value) return;

    if (itemToDelete.value.type === 'achievement') {
        achievementForm.delete(
            achievementsRoutes.deleteMethod.url(itemToDelete.value.id),
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    deleteConfirmOpen.value = false;
                    itemToDelete.value = null;
                },
            },
        );
    } else {
        trainingForm.delete(
            trainingsRoutes.deleteMethod.url(itemToDelete.value.id),
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    deleteConfirmOpen.value = false;
                    itemToDelete.value = null;
                },
            },
        );
    }
};

const editMode = ref(false);
const wizardSteps = ['basic', 'address', 'guardian', 'emergency', 'family', 'education', 'ched'] as const;
const currentStep = ref(0);
const lastSavedAt = ref('');
const provinces = ref<string[]>([]);
const cities = ref<string[]>([]);
const barangays = ref<string[]>([]);
const resCities = ref<string[]>([]);
const resBarangays = ref<string[]>([]);
const incomeRanges = [
    { value: '0-9520', label: '0-9,520 (Poor)' },
    { value: '9520-19040', label: '9,520-19,040 (Low Income)' },
    { value: '19040-38080', label: '19,040-38,080 (Lower Middle Income)' },
    { value: '38080-66640', label: '38,080-66,640 (Middle Income)' },
    { value: '66640-114240', label: '66,640-114,240 (Upper Middle Income)' },
    { value: '114240-190400', label: '114,240-190,400 (Upper Income)' },
    { value: '190400-9999999', label: 'At least PHP 190,400 (Rich)' },
];
const fatherIncomeRange = computed({
    get: () => `${profileForm.fatherIncomeFrom ?? ''}-${profileForm.fatherIncomeTo ?? ''}`,
    set: (v: string) => {
        const [from, to] = String(v || '').split('-');
        profileForm.fatherIncomeFrom = from ?? '';
        profileForm.fatherIncomeTo = to ?? '';
    },
});
const motherIncomeRange = computed({
    get: () => `${profileForm.motherIncomeFrom ?? ''}-${profileForm.motherIncomeTo ?? ''}`,
    set: (v: string) => {
        const [from, to] = String(v || '').split('-');
        profileForm.motherIncomeFrom = from ?? '';
        profileForm.motherIncomeTo = to ?? '';
    },
});

const profileForm = useForm({
    gender: String(props.profile.data?.gender ?? '').trim().toUpperCase(),
    height: props.profile.data?.height ?? '',
    weight: props.profile.data?.weight ?? '',
    bloodType: props.profile.data?.bloodType ?? '',
    placeOfBirth: props.profile.data?.placeOfBirth ?? '',
    mobileNo: props.profile.data?.mobileNo ?? '',
    telNo: props.profile.data?.telNo ?? '',
    permProvince: props.profile.data?.permProvince ?? '',
    permTownCity: props.profile.data?.permTownCity ?? '',
    permBarangay: props.profile.data?.permBarangay ?? '',
    permAddress: props.profile.data?.permAddress ?? '',
    permStreet: props.profile.data?.permStreet ?? '',
    resProvince: props.profile.data?.resProvince ?? '',
    resTownCity: props.profile.data?.resTownCity ?? '',
    resBarangay: props.profile.data?.resBarangay ?? '',
    resAddress: props.profile.data?.resAddress ?? '',
    resStreet: props.profile.data?.resStreet ?? '',
    civilStatusId: props.profile.data?.civilStatusId ?? '',
    religionId: props.profile.data?.religionId ?? '',
    nationalityId: props.profile.data?.nationalityId ?? '',
    tribeId: props.profile.data?.tribeId ?? '',
    permZipCode: props.profile.data?.permZipCode ?? '',
    resZipCode: props.profile.data?.resZipCode ?? '',
    guardian: props.profile.data?.guardian ?? '',
    guardianAddress: props.profile.data?.guardianAddress ?? '',
    guardianTelNo: props.profile.data?.guardianTelNo ?? '',
    guardianEmail: props.profile.data?.guardianEmail ?? '',
    emergencyContact: props.profile.data?.emergencyContact ?? '',
    emergencyAddress: props.profile.data?.emergencyAddress ?? '',
    emergencyMobileNo: props.profile.data?.emergencyMobileNo ?? '',
    emergencyTelNo: props.profile.data?.emergencyTelNo ?? '',
    father: props.profile.data?.father ?? '',
    fatherOccupation: props.profile.data?.fatherOccupation ?? '',
    fatherCompany: props.profile.data?.fatherCompany ?? '',
    fatherCompanyAddress: props.profile.data?.fatherCompanyAddress ?? '',
    fatherTelNo: props.profile.data?.fatherTelNo ?? '',
    fatherEmail: props.profile.data?.fatherEmail ?? '',
    fatherBirthDate: props.profile.data?.fatherBirthDate ?? '',
    fatherEducAttain: props.profile.data?.fatherEducAttain ?? '',
    fatherIncomeFrom: props.profile.data?.fatherIncomeFrom ?? '',
    fatherIncomeTo: props.profile.data?.fatherIncomeTo ?? '',
    fatherCitizenship: props.profile.data?.fatherCitizenship ?? '',
    fatherNatureOfWork: props.profile.data?.fatherNatureOfWork ?? '',
    fatherEducationalAttainment: props.profile.data?.fatherEducationalAttainment ?? '',
    fatherEmploymentStatus: props.profile.data?.fatherEmploymentStatus ?? '',
    mother: props.profile.data?.mother ?? '',
    motherOccupation: props.profile.data?.motherOccupation ?? '',
    motherCompany: props.profile.data?.motherCompany ?? '',
    motherCompanyAddress: props.profile.data?.motherCompanyAddress ?? '',
    motherTelNo: props.profile.data?.motherTelNo ?? '',
    motherEmail: props.profile.data?.motherEmail ?? '',
    motherBirthDate: props.profile.data?.motherBirthDate ?? '',
    motherEducAttain: props.profile.data?.motherEducAttain ?? '',
    motherIncomeFrom: props.profile.data?.motherIncomeFrom ?? '',
    motherIncomeTo: props.profile.data?.motherIncomeTo ?? '',
    motherCitizenship: props.profile.data?.motherCitizenship ?? '',
    motherNatureOfWork: props.profile.data?.motherNatureOfWork ?? '',
    motherEducationalAttainment: props.profile.data?.motherEducationalAttainment ?? '',
    motherEmploymentStatus: props.profile.data?.motherEmploymentStatus ?? '',
    noofBrothers: props.profile.data?.noofBrothers ?? '',
    noofSisters: props.profile.data?.noofSisters ?? '',
    isIllegitimate: props.profile.data?.isIllegitimate ?? false,
    elemSchool: props.profile.data?.elemSchool ?? '',
    elemAddress: props.profile.data?.elemAddress ?? '',
    elemInclDates: props.profile.data?.elemInclDates ?? '',
    elemAwardHonor: props.profile.data?.elemAwardHonor ?? '',
    hsSchool: props.profile.data?.hsSchool ?? '',
    hsAddress: props.profile.data?.hsAddress ?? '',
    hsInclDates: props.profile.data?.hsInclDates ?? '',
    hsAwardHonor: props.profile.data?.hsAwardHonor ?? '',
    vocational: props.profile.data?.vocational ?? '',
    vocationalAddress: props.profile.data?.vocationalAddress ?? '',
    vocationalInclDates: props.profile.data?.vocationalInclDates ?? '',
    collegeSchool: props.profile.data?.collegeSchool ?? '',
    collegeDegree: props.profile.data?.collegeDegree ?? '',
    collegeAddress: props.profile.data?.collegeAddress ?? '',
    collegeInclDates: props.profile.data?.collegeInclDates ?? '',
    shsTrack: props.profile.data?.shsTrack ?? '',
    shsSchool: props.profile.data?.shsSchool ?? '',
    shsIncldates: props.profile.data?.shsIncldates ?? '',
    shsAwardsHonors: props.profile.data?.shsAwardsHonors ?? '',
    lastSchoolAttendedType: props.profile.data?.lastSchoolAttendedType ?? '',
    studentType: props.profile.data?.studentType ?? '',
    studentCategory: props.profile.data?.studentCategory ?? '',
    firstGenerationStudent: props.profile.data?.firstGenerationStudent ?? '',
    isGida: props.profile.data?.isGida ?? '',
    descGida: props.profile.data?.descGida ?? '',
    isBelongToFarmer: props.profile.data?.isBelongToFarmer ?? '',
    isRebelReturnee: props.profile.data?.isRebelReturnee ?? '',
    familySize: props.profile.data?.familySize ?? '',
    ipMember: props.profile.data?.ipMember ?? false,
    ipMemberTribe: props.profile.data?.ipMemberTribe ?? '',
    pwdMember: props.profile.data?.pwdMember ?? false,
    pwdMemberId: props.profile.data?.pwdMemberId ?? '',
    pwdCategory: props.profile.data?.pwdCategory ?? '',
    soloParent: props.profile.data?.soloParent ?? false,
    soloParentId: props.profile.data?.soloParentId ?? '',
    raisedBySoloParent: props.profile.data?.raisedBySoloParent ?? '',
    isAdm: props.profile.data?.isAdm ?? '',
    admSchool: props.profile.data?.admSchool ?? '',
    admSchoolYear: props.profile.data?.admSchoolYear ?? '',
    isAls: props.profile.data?.isAls ?? '',
    alsSchool: props.profile.data?.alsSchool ?? '',
    alsSchoolYear: props.profile.data?.alsSchoolYear ?? '',
});

// --- Auto-save Draft Logic ---
const DRAFT_KEY = `student_profile_draft_${props.profile.data?.studentNo}`;
const saveDraft = () => {
    localStorage.setItem(DRAFT_KEY, JSON.stringify(profileForm.data()));
};
const loadDraft = () => {
    const draft = localStorage.getItem(DRAFT_KEY);
    if (draft) {
        const data = JSON.parse(draft);
        Object.assign(profileForm, data);
        toast.info('Restored your unsaved changes.');
    }
};
const clearDraft = () => localStorage.removeItem(DRAFT_KEY);

watch(profileForm, saveDraft, { deep: true });
// -----------------------------

const loadRefs = async () => {
    const [prov, city, brgy] = await Promise.all([
        fetch('/assets/refprovince.json').then((r) => r.json()),
        fetch('/assets/refcitymun.json').then((r) => r.json()),
        fetch('/assets/refbrgy.json').then((r) => r.json()),
    ]);

    provinces.value = (prov.RECORDS ?? []).map((x: any) => x.provDesc);
    const cityRows = city.RECORDS ?? [];
    const brgyRows = brgy.RECORDS ?? [];

    const allCities = cityRows.map((x: any) => x.citymunDesc);
    const bindPerm = () => {
        // Legacy behavior: municipality list comes from all city/mun records,
        // then barangay is filtered by selected municipality.
        cities.value = allCities;
        barangays.value = brgyRows
            .filter((x: any) => x.citymunDesc === profileForm.permTownCity)
            .map((x: any) => x.brgyDesc);
    };
    const bindRes = () => {
        resCities.value = allCities;
        resBarangays.value = brgyRows
            .filter((x: any) => x.citymunDesc === profileForm.resTownCity)
            .map((x: any) => x.brgyDesc);
    };

    bindPerm(); bindRes();

    watch(() => profileForm.permProvince, () => {
        profileForm.permTownCity = '';
        profileForm.permBarangay = '';
        bindPerm();
    });
    watch(() => profileForm.permTownCity, () => {
        profileForm.permBarangay = '';
        bindPerm();
    });
    watch(() => profileForm.resProvince, () => {
        if (sameAsPermanentAddress.value) {
            bindRes();
            return;
        }
        profileForm.resTownCity = '';
        profileForm.resBarangay = '';
        bindRes();
    });
    watch(() => profileForm.resTownCity, () => {
        if (sameAsPermanentAddress.value) {
            bindRes();
            return;
        }
        profileForm.resBarangay = '';
        bindRes();
    });
};

const formatMobile = (field: 'mobileNo' | 'telNo' | 'guardianTelNo' | 'emergencyMobileNo' | 'emergencyTelNo' | 'fatherTelNo' | 'motherTelNo') => {
    let val = profileForm[field].replace(/\D/g, '');
    if (val.length > 11) val = val.substring(0, 11);
    
    if (val.startsWith('09') && val.length > 2) {
        if (val.length <= 6) val = val.replace(/(\d{4})(\d+)/, '$1-$2');
        else val = val.replace(/(\d{4})(\d{3})(\d+)/, '$1-$2-$3');
    }
    profileForm[field] = val;
};

const sameAsPermanentAddress = ref(false);
const syncAddresses = () => {
    if (!sameAsPermanentAddress.value) return;
    profileForm.resAddress = profileForm.permAddress;
    profileForm.resStreet = profileForm.permStreet;
    profileForm.resZipCode = profileForm.permZipCode;
    profileForm.resProvince = profileForm.permProvince;
    profileForm.resTownCity = profileForm.permTownCity;
    profileForm.resBarangay = profileForm.permBarangay;
};

watch(sameAsPermanentAddress, (val) => {
    if (val) {
        syncAddresses();
    }
});
watch(() => profileForm.permAddress, syncAddresses);
watch(() => profileForm.permStreet, syncAddresses);
watch(() => profileForm.permZipCode, syncAddresses);
watch(() => profileForm.permProvince, syncAddresses);
watch(() => profileForm.permTownCity, syncAddresses);
watch(() => profileForm.permBarangay, syncAddresses);

const saveProfile = () => {
    profileForm.gender = String(profileForm.gender ?? '').trim().toUpperCase();
    profileForm.patch('/student-profile', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editMode.value = false;
            clearDraft();
            lastSavedAt.value = new Date().toLocaleString();
        },
        onError: (errors) => {
            toast.error((errors as any)?.gender ?? 'Unable to save profile.');
        },
    });
};

const requiredFields = [
    'gender','height','weight','bloodType','placeOfBirth','mobileNo',
    'permAddress','permStreet','permZipCode','permProvince','permTownCity','permBarangay',
    'resAddress','resStreet','resZipCode','resProvince','resTownCity','resBarangay',
    'guardian','guardianAddress','guardianTelNo',
    'emergencyContact','emergencyAddress','emergencyMobileNo',
] as const;

const completeness = computed(() => {
    const total = requiredFields.length;
    const done = requiredFields.filter((k) => {
        const v = (profileForm as any)[k];
        return v !== null && v !== undefined && String(v).trim() !== '';
    }).length;
    return Math.round((done / total) * 100);
});

const stepTitle = computed(() => {
    const titles = ['Basic Details', 'Address', 'Guardian', 'Emergency Contact', 'Family Background', 'Educational Background', 'Additional Details for CHED'];
    return titles[currentStep.value] ?? 'Profile Wizard';
});

const nextStep = () => {
    if (currentStep.value < wizardSteps.length - 1) {
        currentStep.value += 1;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};
const previousStep = () => {
    if (currentStep.value > 0) {
        currentStep.value -= 1;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

onMounted(() => {
    loadRefs();
    if (editMode.value) loadDraft();
});
watch(editMode, (val) => { if (val) loadDraft(); });
</script>

<template>
    <Head title="My Profile" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-5">
        <div
            v-if="profile.error"
            class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200"
        >
            <AlertCircle class="mt-0.5 size-4 shrink-0" />
            <span>{{ profile.error }}</span>
        </div>

        <Transition name="fade-transform" mode="out-in">
            <!-- View Mode -->
            <div v-if="profile.data && !editMode" key="view-mode" class="flex flex-col gap-4">
                <section
                    class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 md:flex-row md:items-center md:justify-between dark:border-white/10"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 text-sm font-bold text-slate-800 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            >
                                <img
                                    v-if="studentPictureUrl"
                                    :src="studentPictureUrl"
                                    alt="Student Picture"
                                    class="size-full object-cover"
                                />
                                <span v-else>{{ getInitials(profile.data.firstName) }}</span>
                            </div>
                            <div class="min-w-0">
                                <h1
                                    class="truncate text-lg font-bold text-slate-950 dark:text-white"
                                >
                                    {{ fullName }}
                                </h1>
                                <div
                                    class="mt-1 flex flex-wrap items-center gap-3 text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        <IdCard class="size-3.5" />
                                        {{ profile.data.studentNo }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <School class="size-3.5" />
                                        {{ profile.data.statusRemarks || '-' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                            @click="editMode = true"
                        >
                            <Edit class="size-4" />
                            Edit Profile
                        </button>
                    </div>

                    <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"
                        >
                            <div
                                class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                            >
                                <User class="size-4 text-sky-600" />
                                Student No.
                            </div>
                            <div
                                class="mt-2 truncate text-lg font-bold text-slate-950 dark:text-white"
                            >
                                {{ profile.data.studentNo || '-' }}
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"
                        >
                            <div
                                class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                            >
                                <Mail class="size-4 text-emerald-600" />
                                Email
                            </div>
                            <div
                                class="mt-2 truncate text-lg font-bold text-slate-950 dark:text-white"
                            >
                                {{ profile.data.email || '-' }}
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"
                        >
                            <div
                                class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                            >
                                <Phone class="size-4 text-amber-600" />
                                Mobile
                            </div>
                            <div
                                class="mt-2 truncate text-lg font-bold text-slate-950 dark:text-white"
                            >
                                {{ profile.data.mobileNo || '-' }}
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"
                        >
                            <div
                                class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                            >
                                <ShieldCheck class="size-4 text-violet-600" />
                                Records
                            </div>
                            <div
                                class="mt-2 text-lg font-bold text-slate-950 dark:text-white"
                            >
                                {{ achievements.length + trainings.length }}
                            </div>
                        </div>
                    </div>
                </section>

                <div class="grid gap-4 xl:grid-cols-[240px_1fr]">
                    <aside
                        class="rounded-lg border border-slate-200 bg-white p-2 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-bold transition"
                            :class="
                                activeTab === tab.id
                                    ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                                    : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5'
                            "
                            @click="activeTab = tab.id"
                        >
                            <component :is="tab.icon" class="size-4" />
                            {{ tab.label }}
                        </button>
                    </aside>

                    <section
                        class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <div
                            class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10"
                        >
                            <div>
                                <h2
                                    class="text-sm font-bold text-slate-950 dark:text-white"
                                >
                                    {{
                                        tabs.find((tab) => tab.id === activeTab)
                                            ?.label
                                    }}
                                </h2>
                                <p
                                    class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Student profile information synced with the
                                    academic system.
                                </p>
                            </div>

                            <button
                                v-if="activeTab === 'achievements'"
                                type="button"
                                class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                @click="openAchievementModal()"
                            >
                                <Plus class="size-4" />
                                Add
                            </button>
                            <button
                                v-if="activeTab === 'trainings'"
                                type="button"
                                class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                @click="openTrainingModal()"
                            >
                                <Plus class="size-4" />
                                Add
                            </button>
                        </div>

                        <div class="p-4">
                            <div
                                v-if="activeTab === 'personal'"
                                class="grid gap-4 lg:grid-cols-[1fr_1fr]"
                            >
                                <div
                                    class="rounded-lg border border-slate-200 dark:border-white/10"
                                >
                                    <div
                                        class="border-b border-slate-200 px-3 py-2 text-xs font-bold text-slate-500 uppercase dark:border-white/10 dark:text-slate-400"
                                    >
                                        Basic Information
                                    </div>
                                    <dl
                                        class="grid gap-0 divide-y divide-slate-100 dark:divide-white/10"
                                    >
                                        <div
                                            v-for="field in personalFields"
                                            :key="field.label"
                                            class="grid grid-cols-[150px_1fr] gap-3 px-3 py-2 text-xs"
                                        >
                                            <dt
                                                class="font-bold text-slate-500 dark:text-slate-400"
                                            >
                                                {{ field.label }}
                                            </dt>
                                            <dd
                                                class="font-semibold text-slate-900 dark:text-white"
                                            >
                                                {{ field.value }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <div
                                    class="rounded-lg border border-slate-200 dark:border-white/10"
                                >
                                    <div
                                        class="border-b border-slate-200 px-3 py-2 text-xs font-bold text-slate-500 uppercase dark:border-white/10 dark:text-slate-400"
                                    >
                                        Contact
                                    </div>
                                    <div class="grid gap-2 p-3">
                                        <div
                                            class="flex items-center gap-3 rounded-md bg-slate-50 px-3 py-2 text-xs dark:bg-white/5"
                                        >
                                            <Mail class="size-4 text-slate-500" />
                                            <span
                                                class="truncate font-bold text-slate-900 dark:text-white"
                                                >{{
                                                    profile.data.email || '-'
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            class="flex items-center gap-3 rounded-md bg-slate-50 px-3 py-2 text-xs dark:bg-white/5"
                                        >
                                            <Phone class="size-4 text-slate-500" />
                                            <span
                                                class="truncate font-bold text-slate-900 dark:text-white"
                                                >{{
                                                    profile.data.mobileNo || '-'
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="rounded-lg border border-slate-200 lg:col-span-2 dark:border-white/10"
                                >
                                    <div
                                        class="border-b border-slate-200 px-3 py-2 text-xs font-bold text-slate-500 uppercase dark:border-white/10 dark:text-slate-400"
                                    >
                                        Address
                                    </div>
                                    <div
                                        class="grid gap-3 p-3 text-xs sm:grid-cols-2"
                                    >
                                        <div
                                            class="rounded-md bg-slate-50 p-3 dark:bg-white/5"
                                        >
                                            <div
                                                class="mb-1 flex items-center gap-2 font-bold text-slate-500 dark:text-slate-400"
                                            >
                                                <Home class="size-4" />
                                                Residential
                                            </div>
                                            <p
                                                class="font-semibold text-slate-900 dark:text-white"
                                            >
                                                {{ profile.data.resAddress }}
                                                {{ profile.data.resStreet }}
                                                {{ profile.data.resBarangay }}
                                            </p>
                                            <p
                                                class="mt-1 text-slate-500 dark:text-slate-400"
                                            >
                                                {{ profile.data.resTownCity }}
                                                {{ profile.data.resProvince }}
                                            </p>
                                        </div>
                                        <div
                                            class="rounded-md bg-slate-50 p-3 dark:bg-white/5"
                                        >
                                            <div
                                                class="mb-1 flex items-center gap-2 font-bold text-slate-500 dark:text-slate-400"
                                            >
                                                <Home class="size-4" />
                                                Permanent
                                            </div>
                                            <p
                                                class="font-semibold text-slate-900 dark:text-white"
                                            >
                                                {{
                                                    profile.data.permAddress || '-'
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="activeTab === 'academic'"
                                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                            >
                                <div
                                    v-for="field in academicFields"
                                    :key="field.label"
                                    class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"
                                >
                                    <div
                                        class="text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{ field.label }}
                                    </div>
                                    <div
                                        class="mt-2 truncate text-lg font-bold text-slate-950 dark:text-white"
                                    >
                                        {{ field.value }}
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="activeTab === 'family'"
                                class="rounded-lg border border-slate-200 dark:border-white/10"
                            >
                                <dl
                                    class="divide-y divide-slate-100 dark:divide-white/10"
                                >
                                    <div
                                        v-for="field in familyFields"
                                        :key="field.label"
                                        class="grid grid-cols-[150px_1fr] gap-3 px-3 py-3 text-xs"
                                    >
                                        <dt
                                            class="font-bold text-slate-500 dark:text-slate-400"
                                        >
                                            {{ field.label }}
                                        </dt>
                                        <dd
                                            class="font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ field.value }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div
                                v-if="activeTab === 'education'"
                                class="grid gap-3"
                            >
                                <div
                                    v-for="field in educationFields"
                                    :key="field.label"
                                    class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-xs dark:border-white/10"
                                >
                                    <BookOpen
                                        class="mt-0.5 size-4 shrink-0 text-slate-500"
                                    />
                                    <div class="min-w-0">
                                        <div
                                            class="font-bold text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{ field.label }}
                                        </div>
                                        <div
                                            class="mt-1 font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ field.value }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="activeTab === 'achievements'">
                                <div
                                    v-if="achievements.length === 0"
                                    class="flex min-h-[240px] flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-slate-200 p-6 text-center dark:border-white/10"
                                >
                                    <Trophy class="size-9 text-slate-300" />
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        No awards added yet
                                    </p>
                                </div>

                                <div
                                    v-else
                                    class="overflow-hidden rounded-lg border border-slate-200 dark:border-white/10"
                                >
                                    <table class="w-full min-w-[720px] text-sm">
                                        <thead
                                            class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                                        >
                                            <tr>
                                                <th class="px-4 py-3">Title</th>
                                                <th class="px-4 py-3">Awarder</th>
                                                <th class="px-4 py-3">Date</th>
                                                <th class="px-4 py-3 text-right">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="divide-y divide-slate-100 dark:divide-white/10"
                                        >
                                            <tr
                                                v-for="item in achievements"
                                                :key="item.id"
                                                class="hover:bg-slate-50 dark:hover:bg-white/5"
                                            >
                                                <td class="px-4 py-3">
                                                    <div
                                                        class="font-bold text-slate-900 dark:text-white"
                                                    >
                                                        {{ item.title }}
                                                    </div>
                                                    <div
                                                        class="text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            item.description || '-'
                                                        }}
                                                    </div>
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                                >
                                                    {{ item.awarder || '-' }}
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                                >
                                                    {{
                                                        formatDate(
                                                            item.date_received,
                                                        )
                                                    }}
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <div
                                                        class="flex justify-end gap-1"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white"
                                                            @click="
                                                                openAchievementModal(
                                                                    item,
                                                                )
                                                            "
                                                        >
                                                            <Edit class="size-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="inline-flex size-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10"
                                                            @click="
                                                                confirmDelete(
                                                                    item.id,
                                                                    'achievement',
                                                                    item.title,
                                                                )
                                                            "
                                                        >
                                                            <Trash2
                                                                class="size-4"
                                                            />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div v-if="activeTab === 'trainings'">
                                <div
                                    v-if="trainings.length === 0"
                                    class="flex min-h-[240px] flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-slate-200 p-6 text-center dark:border-white/10"
                                >
                                    <Calendar class="size-9 text-slate-300" />
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        No training records found
                                    </p>
                                </div>

                                <div
                                    v-else
                                    class="overflow-hidden rounded-lg border border-slate-200 dark:border-white/10"
                                >
                                    <table class="w-full min-w-[720px] text-sm">
                                        <thead
                                            class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                                        >
                                            <tr>
                                                <th class="px-4 py-3">Title</th>
                                                <th class="px-4 py-3">Organizer</th>
                                                <th class="px-4 py-3">Date</th>
                                                <th class="px-4 py-3 text-right">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="divide-y divide-slate-100 dark:divide-white/10"
                                        >
                                            <tr
                                                v-for="item in trainings"
                                                :key="item.id"
                                                class="hover:bg-slate-50 dark:hover:bg-white/5"
                                            >
                                                <td
                                                    class="px-4 py-3 font-bold text-slate-900 dark:text-white"
                                                >
                                                    {{ item.title }}
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                                >
                                                    {{ item.organizer || '-' }}
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                                >
                                                    {{ formatDate(item.date_from) }}
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <div
                                                        class="flex justify-end gap-1"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white"
                                                            @click="
                                                                openTrainingModal(
                                                                    item,
                                                                )
                                                            "
                                                        >
                                                            <Edit class="size-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="inline-flex size-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10"
                                                            @click="
                                                                confirmDelete(
                                                                    item.id,
                                                                    'training',
                                                                    item.title,
                                                                )
                                                            "
                                                        >
                                                            <Trash2
                                                                class="size-4"
                                                            />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div
                                v-if="activeTab === 'socio'"
                                class="rounded-lg border border-slate-200 dark:border-white/10"
                            >
                                <dl
                                    class="divide-y divide-slate-100 dark:divide-white/10"
                                >
                                    <div
                                        v-for="field in socioFields"
                                        :key="field.label"
                                        class="grid grid-cols-[150px_1fr] gap-3 px-3 py-3 text-xs"
                                    >
                                        <dt
                                            class="font-bold text-slate-500 dark:text-slate-400"
                                        >
                                            {{ field.label }}
                                        </dt>
                                        <dd
                                            class="font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ field.value }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Edit Mode (Wizard) -->
            <section
                v-else
                key="edit-mode"
                class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
            >
                <!-- Sticky Header for Editor -->
                <div class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-5 py-5 backdrop-blur-md sm:px-8 sm:py-6 dark:border-white/10 dark:bg-slate-950/95">
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                             <div class="flex size-12 items-center justify-center rounded-full bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                                <Edit class="size-6" />
                             </div>
                             <div>
                                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">Profile Editor</h3>
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Update your records</p>
                             </div>
                        </div>
                        <div class="flex flex-col items-end text-right">
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ stepTitle }}</span>
                            <span class="text-xs font-semibold text-slate-400">Step {{ currentStep + 1 }} of {{ wizardSteps.length }}</span>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="px-2">
                        <div class="flex items-center justify-between">
                            <div v-for="(step, index) in wizardSteps" :key="step" class="flex flex-1 items-center">
                                <div 
                                    class="flex size-8 items-center justify-center rounded-full border-2 text-xs font-bold transition-all duration-300 cursor-pointer"
                                    :class="[
                                        index <= currentStep 
                                            ? 'border-sky-600 bg-sky-600 text-white shadow-[0_0_10px_rgba(2,132,199,0.3)]' 
                                            : 'border-slate-200 bg-white text-slate-400 hover:border-slate-300 dark:border-white/10 dark:bg-slate-900'
                                    ]"
                                    @click="currentStep = index"
                                >
                                    <span v-if="index < currentStep">✓</span>
                                    <span v-else>{{ index + 1 }}</span>
                                </div>
                                <div 
                                    v-if="index < wizardSteps.length - 1" 
                                    class="h-1 flex-1 transition-all duration-500"
                                    :class="[index < currentStep ? 'bg-sky-600' : 'bg-slate-100 dark:bg-white/5']"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wizard Body -->
                <div class="flex-1 px-5 py-6 sm:px-8 sm:py-8 min-h-[500px]">
                     <div class="mb-8 flex items-center justify-between rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm font-semibold text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/5 dark:text-sky-300">
                        <div class="flex items-center gap-2">
                            <Info class="size-5" />
                            <span>Profile Completeness: {{ completeness }}%</span>
                        </div>
                        <span v-if="lastSavedAt" class="text-xs opacity-70 italic text-slate-500">Auto-saved at {{ lastSavedAt }}</span>
                    </div>

                    <!-- Step 0: Basic Details -->
                    <div v-if="currentStep === 0">
                        <h3 class="mb-6 text-lg font-extrabold text-slate-900 dark:text-white uppercase tracking-tight">Basic Details</h3>
                        <div class="grid gap-5 md:grid-cols-4">
                            <div><Label class="mb-1.5 block text-xs">Gender <span class="text-red-500">*</span></Label><select v-model="profileForm.gender" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"><option value="">---</option><option value="M">Male</option><option value="F">Female</option></select></div>
                            <div><Label class="mb-1.5 block text-xs">Date of Birth</Label><Input :model-value="profile.data?.dateOfBirth ? new Date(profile.data.dateOfBirth).toISOString().slice(0,10) : ''" readonly class="bg-slate-50 dark:bg-white/5" /></div>
                            <div><Label class="mb-1.5 block text-xs">Height (cm) <span class="text-red-500">*</span></Label><Input v-model="profileForm.height" type="number" /></div>
                            <div><Label class="mb-1.5 block text-xs">Weight (kg) <span class="text-red-500">*</span></Label><Input v-model="profileForm.weight" type="number" /></div>
                                <div><Label class="mb-1.5 block text-xs">Blood Type <span class="text-red-500">*</span></Label><Input v-model="profileForm.bloodType" /></div>
                                <div class="md:col-span-3"><Label class="mb-1.5 block text-xs">Place of Birth <span class="text-red-500">*</span></Label><Input v-model="profileForm.placeOfBirth" /></div>
                            <div><Label class="mb-1.5 block text-xs">Tribe: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.tribeId" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option :value="''">---</option><option v-for="t in (props.lookups?.tribes ?? [])" :key="t.tribeId" :value="t.tribeId">{{ t.tribeName }}</option></select></div>
                            <div><Label class="mb-1.5 block text-xs">Civil Status: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.civilStatusId" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option :value="''">---</option><option v-for="c in (props.lookups?.civilStatuses ?? [])" :key="c.statusId" :value="c.statusId">{{ c.civilDesc }}</option></select></div>
                            <div><Label class="mb-1.5 block text-xs">Religion: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.religionId" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option :value="''">---</option><option v-for="r in (props.lookups?.religions ?? [])" :key="r.religionId" :value="r.religionId">{{ r.religion }}</option></select></div>
                            <div><Label class="mb-1.5 block text-xs">Nationality: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.nationalityId" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option :value="''">---</option><option v-for="n in (props.lookups?.nationalities ?? [])" :key="n.nationalityId" :value="n.nationalityId">{{ n.nationality }}</option></select></div>
                            <div class="md:col-span-2"><Label class="mb-1.5 block text-xs">Mobile No. <span class="text-red-500">*</span></Label><Input v-model="profileForm.mobileNo" placeholder="09XX-XXX-XXXX" @input="formatMobile('mobileNo')" /></div>
                            <div class="md:col-span-2"><Label class="mb-1.5 block text-xs">Telephone No.</Label><Input v-model="profileForm.telNo" @input="formatMobile('telNo')" /></div>
                        </div>
                    </div>

                    <!-- Step 1: Address -->
                    <div v-if="currentStep === 1">
                        <h3 class="mb-2 text-lg font-extrabold text-slate-900 dark:text-white uppercase tracking-tight">Address</h3>
                        <p class="mb-8 text-sm text-slate-500">Please provide accurate address information for university correspondence.</p>
                        
                        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5">
                            <h4 class="mb-5 text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Permanent Address</h4>
                            <div class="grid gap-5 md:grid-cols-4">
                                <div class="md:col-span-2"><Label class="mb-1.5 block text-xs">House/Block/Lot No. <span class="text-red-500">*</span></Label><Input v-model="profileForm.permAddress" /></div>
                                <div class="md:col-span-2"><Label class="mb-1.5 block text-xs">Street <span class="text-red-500">*</span></Label><Input v-model="profileForm.permStreet" /></div>
                                <div><Label class="mb-1.5 block text-xs">Province <span class="text-red-500">*</span></Label><select v-model="profileForm.permProvince" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"><option value="">---</option><option v-for="p in provinces" :key="p" :value="p">{{ p }}</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">Municipality/City <span class="text-red-500">*</span></Label><select v-model="profileForm.permTownCity" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"><option value="">---</option><option v-for="c in cities" :key="c" :value="c">{{ c }}</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">Barangay <span class="text-red-500">*</span></Label><select v-model="profileForm.permBarangay" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"><option value="">---</option><option v-for="b in barangays" :key="b" :value="b">{{ b }}</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">Zip Code <span class="text-red-500">*</span></Label><Input v-model="profileForm.permZipCode" type="number" /></div>
                            </div>
                        </div>

                        <div class="my-6 flex items-center gap-3 rounded-lg border border-sky-200 bg-sky-50 p-4 shadow-sm transition-all hover:bg-sky-100/50 dark:border-sky-500/20 dark:bg-sky-500/5 dark:hover:bg-sky-500/10">
                            <input id="same-as-perm" v-model="sameAsPermanentAddress" type="checkbox" class="size-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                            <Label for="same-as-perm" class="cursor-pointer text-sm font-bold text-sky-800 dark:text-sky-300">Residential address is the same as permanent address</Label>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 transition-opacity dark:border-white/10 dark:bg-white/5" :class="{ 'opacity-50 pointer-events-none': sameAsPermanentAddress }">
                            <h4 class="mb-5 text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Current / Residential Address</h4>
                            <div class="grid gap-5 md:grid-cols-4">
                                <div class="md:col-span-2"><Label class="mb-1.5 block text-xs">House/Block/Lot No. <span class="text-red-500">*</span></Label><Input v-model="profileForm.resAddress" :disabled="sameAsPermanentAddress" /></div>
                                <div class="md:col-span-2"><Label class="mb-1.5 block text-xs">Street <span class="text-red-500">*</span></Label><Input v-model="profileForm.resStreet" :disabled="sameAsPermanentAddress" /></div>
                                <div><Label class="mb-1.5 block text-xs">Province <span class="text-red-500">*</span></Label><select v-model="profileForm.resProvince" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:opacity-50 dark:border-white/10 dark:bg-slate-950" :disabled="sameAsPermanentAddress"><option value="">---</option><option v-for="p in provinces" :key="`r-${p}`" :value="p">{{ p }}</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">Municipality/City <span class="text-red-500">*</span></Label><select v-model="profileForm.resTownCity" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:opacity-50 dark:border-white/10 dark:bg-slate-950" :disabled="sameAsPermanentAddress"><option value="">---</option><option v-for="c in resCities" :key="`r-${c}`" :value="c">{{ c }}</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">Barangay <span class="text-red-500">*</span></Label><select v-model="profileForm.resBarangay" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:opacity-50 dark:border-white/10 dark:bg-slate-950" :disabled="sameAsPermanentAddress"><option value="">---</option><option v-for="b in resBarangays" :key="`r-${b}`" :value="b">{{ b }}</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">Zip Code <span class="text-red-500">*</span></Label><Input v-model="profileForm.resZipCode" type="number" :disabled="sameAsPermanentAddress" /></div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Guardian -->
                    <div v-if="currentStep === 2">
                        <h3 class="mb-6 text-lg font-extrabold text-slate-900 dark:text-white uppercase tracking-tight">GUARDIAN</h3>
                        <div class="grid gap-5 md:grid-cols-2">
                            <div><Label class="mb-1.5 block text-xs">Guardian Name: <span class="text-red-500"><sup>* required</sup></span></Label><Input v-model="profileForm.guardian" /></div>
                            <div><Label class="mb-1.5 block text-xs">Guardian Address: <span class="text-red-500"><sup>* required</sup></span></Label><Input v-model="profileForm.guardianAddress" /></div>
                            <div><Label class="mb-1.5 block text-xs">Guardian Mobile No: <span class="text-red-500"><sup>* required</sup></span></Label><Input v-model="profileForm.guardianTelNo" placeholder="09XX-XXX-XXXX" @input="formatMobile('guardianTelNo')" /></div>
                            <div><Label class="mb-1.5 block text-xs">Guardian Email:</Label><Input v-model="profileForm.guardianEmail" type="email" /></div>
                        </div>
                    </div>

                    <!-- Step 3: Emergency -->
                    <div v-if="currentStep === 3">
                        <h3 class="mb-6 text-lg font-extrabold text-slate-900 dark:text-white uppercase tracking-tight">EMERGENCY CONTACT</h3>
                        <div class="grid gap-5 md:grid-cols-2">
                            <div><Label class="mb-1.5 block text-xs">Emergency Contact Person: <span class="text-red-500"><sup>* required</sup></span></Label><Input v-model="profileForm.emergencyContact" /></div>
                            <div><Label class="mb-1.5 block text-xs">Emergency Address <span class="text-red-500">*</span></Label><Input v-model="profileForm.emergencyAddress" /></div>
                            <div><Label class="mb-1.5 block text-xs">Mobile Number <span class="text-red-500">*</span></Label><Input v-model="profileForm.emergencyMobileNo" placeholder="09XX-XXX-XXXX" @input="formatMobile('emergencyMobileNo')" /></div>
                            <div><Label class="mb-1.5 block text-xs">Telephone Number</Label><Input v-model="profileForm.emergencyTelNo" @input="formatMobile('emergencyTelNo')" /></div>
                        </div>
                    </div>

                    <!-- Step 4: Family Background -->
                    <div v-if="currentStep === 4">
                        <h3 class="mb-8 text-lg font-extrabold text-slate-900 dark:text-white uppercase tracking-tight">FAMILY BACKGROUND</h3>
                        
                        <div class="mb-8 rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5">
                            <h4 class="mb-5 flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-200">
                                <User class="size-4 text-sky-600" /> Father's Information
                            </h4>
                            <div class="grid gap-5 md:grid-cols-3">
                                <div><Label class="mb-1.5 block text-xs">Father's Name <span class="text-red-500">*</span></Label><Input v-model="profileForm.father" /></div>
                                <div><Label class="mb-1.5 block text-xs">Date of Birth <span class="text-red-500">*</span></Label><Input v-model="profileForm.fatherBirthDate" type="date" /></div>
                                <div><Label class="mb-1.5 block text-xs">Educational Attainment <span class="text-red-500">*</span></Label><Input v-model="profileForm.fatherEducAttain" /></div>
                                
                                <div><Label class="mb-1.5 block text-xs">Occupation <span class="text-red-500">*</span></Label><Input v-model="profileForm.fatherOccupation" /></div>
                                <div><Label class="mb-1.5 block text-xs">Company <span class="text-red-500">*</span></Label><Input v-model="profileForm.fatherCompany" /></div>
                                <div><Label class="mb-1.5 block text-xs">Company Address <span class="text-red-500">*</span></Label><Input v-model="profileForm.fatherCompanyAddress" /></div>
                                
                                <div class="md:col-span-1"><Label class="mb-1.5 block text-xs">Contact Number <span class="text-red-500">*</span></Label><Input v-model="profileForm.fatherTelNo" placeholder="09XX-XXX-XXXX" @input="formatMobile('fatherTelNo')" /></div>
                                <div class="md:col-span-2"><Label class="mb-1.5 block text-xs">Email Address <span class="text-red-500">*</span></Label><Input v-model="profileForm.fatherEmail" type="email" /></div>
                                <div><Label class="mb-1.5 block text-xs">Father Income: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="fatherIncomeRange" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">---</option><option v-for="x in incomeRanges" :key="`f-${x.value}`" :value="x.value">{{ x.label }}</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">Father Citizenship: <span class="text-red-500"><sup>* required</sup></span></Label><Input v-model="profileForm.fatherCitizenship" /></div>
                                <div><Label class="mb-1.5 block text-xs">Fathers Nature of Work: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.fatherNatureOfWork" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">Please Select</option><option :value="1">Armed Forces</option><option :value="2">Managers</option><option :value="3">Professionals</option><option :value="4">Technicians and Associate Professionals</option><option :value="5">Clerical Support Workers</option><option :value="6">Service and Sales Workers</option><option :value="7">Skilled Agricultural, Forestry and Fishery Workers</option><option :value="8">Craft and Related Trades Workers</option><option :value="9">Plant and Machine Operators and Assemblers</option><option :value="10">Elementary Occupations</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">What is your Fathers Highest Educational Attainment?: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.fatherEducationalAttainment" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">Please Select</option><option>No formal Education</option><option>Elementary Level</option><option>Elementary Graduate</option><option>Junior High School Level</option><option>Junior High School Graduate</option><option>Senior High School Level</option><option>Senior High School Graduate</option><option>Vocational/Technical Course</option><option>College Level</option><option>College Graduate</option><option>Master's Degree</option><option>Doctorate Degree</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">What is your Fathers Employment Status?: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.fatherEmploymentStatus" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">Please Select</option><option :value="1">Unemployed, but is not seeking for employment</option><option :value="2">Unemployed, but is actively seeking for employment</option><option :value="3">Self-Employed</option><option :value="4">Employed-Government</option><option :value="5">Employed-Private</option><option :value="6">Not Applicable</option></select></div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5">
                            <h4 class="mb-5 flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-200">
                                <User class="size-4 text-pink-600" /> Mother's Information
                            </h4>
                            <div class="grid gap-5 md:grid-cols-3">
                                <div><Label class="mb-1.5 block text-xs">Mother's Name <span class="text-red-500">*</span></Label><Input v-model="profileForm.mother" /></div>
                                <div><Label class="mb-1.5 block text-xs">Date of Birth <span class="text-red-500">*</span></Label><Input v-model="profileForm.motherBirthDate" type="date" /></div>
                                <div><Label class="mb-1.5 block text-xs">Educational Attainment <span class="text-red-500">*</span></Label><Input v-model="profileForm.motherEducAttain" /></div>
                                
                                <div><Label class="mb-1.5 block text-xs">Occupation <span class="text-red-500">*</span></Label><Input v-model="profileForm.motherOccupation" /></div>
                                <div><Label class="mb-1.5 block text-xs">Company <span class="text-red-500">*</span></Label><Input v-model="profileForm.motherCompany" /></div>
                                <div><Label class="mb-1.5 block text-xs">Company Address <span class="text-red-500">*</span></Label><Input v-model="profileForm.motherCompanyAddress" /></div>
                                
                                <div class="md:col-span-1"><Label class="mb-1.5 block text-xs">Contact Number <span class="text-red-500">*</span></Label><Input v-model="profileForm.motherTelNo" placeholder="09XX-XXX-XXXX" @input="formatMobile('motherTelNo')" /></div>
                                <div class="md:col-span-2"><Label class="mb-1.5 block text-xs">Email Address <span class="text-red-500">*</span></Label><Input v-model="profileForm.motherEmail" type="email" /></div>
                                <div><Label class="mb-1.5 block text-xs">Mother Income: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="motherIncomeRange" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">---</option><option v-for="x in incomeRanges" :key="`m-${x.value}`" :value="x.value">{{ x.label }}</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">Mother Citizenship: <span class="text-red-500"><sup>* required</sup></span></Label><Input v-model="profileForm.motherCitizenship" /></div>
                                <div><Label class="mb-1.5 block text-xs">Mothers Nature of Work: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.motherNatureOfWork" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">Please Select</option><option :value="1">Armed Forces</option><option :value="2">Managers</option><option :value="3">Professionals</option><option :value="4">Technicians and Associate Professionals</option><option :value="5">Clerical Support Workers</option><option :value="6">Service and Sales Workers</option><option :value="7">Skilled Agricultural, Forestry and Fishery Workers</option><option :value="8">Craft and Related Trades Workers</option><option :value="9">Plant and Machine Operators and Assemblers</option><option :value="10">Elementary Occupations</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">What is your Mother Highest Educational Attainment?: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.motherEducationalAttainment" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">Please Select</option><option>No formal Education</option><option>Elementary Level</option><option>Elementary Graduate</option><option>Junior High School Level</option><option>Junior High School Graduate</option><option>Senior High School Level</option><option>Senior High School Graduate</option><option>Vocational/Technical Course</option><option>College Level</option><option>College Graduate</option><option>Master's Degree</option><option>Doctorate Degree</option></select></div>
                                <div><Label class="mb-1.5 block text-xs">What is your Mother Employment Status?: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.motherEmploymentStatus" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">Please Select</option><option :value="1">Unemployed, but is not seeking for employment</option><option :value="2">Unemployed, but is actively seeking for employment</option><option :value="3">Self-Employed</option><option :value="4">Employed-Government</option><option :value="5">Employed-Private</option><option :value="6">Not Applicable</option></select></div>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-5 md:grid-cols-3">
                            <div><Label class="mb-1.5 block text-xs">Number of Brothers <span class="text-red-500">*</span></Label><Input v-model="profileForm.noofBrothers" type="number" /></div>
                            <div><Label class="mb-1.5 block text-xs">Number of Sisters <span class="text-red-500">*</span></Label><Input v-model="profileForm.noofSisters" type="number" /></div>
                            <div><Label class="mb-1.5 block text-xs">Legitimate Child? <span class="text-red-500">*</span></Label><select v-model="profileForm.isIllegitimate" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option :value="true">Yes</option><option :value="false">No</option></select></div>
                        </div>
                    </div>

                    <!-- Step 5: Education -->
                    <div v-if="currentStep === 5">
                        <h3 class="mb-8 text-lg font-extrabold text-slate-900 dark:text-white uppercase tracking-tight">EDUCATIONAL BACKGROUND</h3>
                        
                        <div class="mb-8 rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5">
                            <h4 class="mb-5 text-sm font-bold text-slate-800 dark:text-slate-200">Elementary School</h4>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div><Label class="mb-1.5 block text-xs">School Name <span class="text-red-500">*</span></Label><Input v-model="profileForm.elemSchool" /></div>
                                <div><Label class="mb-1.5 block text-xs">Address <span class="text-red-500">*</span></Label><Input v-model="profileForm.elemAddress" /></div>
                                <div><Label class="mb-1.5 block text-xs">Inclusive Dates <span class="text-red-500">*</span></Label><Input v-model="profileForm.elemInclDates" placeholder="e.g. 2010 - 2016" /></div>
                                <div><Label class="mb-1.5 block text-xs">Awards & Honors (Optional)</Label><Input v-model="profileForm.elemAwardHonor" /></div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5">
                            <h4 class="mb-5 text-sm font-bold text-slate-800 dark:text-slate-200">High School</h4>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div><Label class="mb-1.5 block text-xs">School Name <span class="text-red-500">*</span></Label><Input v-model="profileForm.hsSchool" /></div>
                                <div><Label class="mb-1.5 block text-xs">Address <span class="text-red-500">*</span></Label><Input v-model="profileForm.hsAddress" /></div>
                                <div><Label class="mb-1.5 block text-xs">Inclusive Dates <span class="text-red-500">*</span></Label><Input v-model="profileForm.hsInclDates" placeholder="e.g. 2016 - 2020" /></div>
                                <div><Label class="mb-1.5 block text-xs">Awards & Honors (Optional)</Label><Input v-model="profileForm.hsAwardHonor" /></div>
                            </div>
                        </div>
                        <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5">
                            <h4 class="mb-5 text-sm font-bold text-slate-800 dark:text-slate-200">Vocational / College</h4>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div><Label class="mb-1.5 block text-xs">Vocational/Trade School</Label><Input v-model="profileForm.vocational" /></div>
                                <div><Label class="mb-1.5 block text-xs">Address</Label><Input v-model="profileForm.vocationalAddress" /></div>
                                <div><Label class="mb-1.5 block text-xs">Inclusive Dates</Label><Input v-model="profileForm.vocationalInclDates" /></div>
                                <div><Label class="mb-1.5 block text-xs">College School</Label><Input v-model="profileForm.collegeSchool" /></div>
                                <div><Label class="mb-1.5 block text-xs">Degree/Course</Label><Input v-model="profileForm.collegeDegree" /></div>
                                <div><Label class="mb-1.5 block text-xs">Address</Label><Input v-model="profileForm.collegeAddress" /></div>
                                <div><Label class="mb-1.5 block text-xs">Inclusive Dates</Label><Input v-model="profileForm.collegeInclDates" /></div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 6: CHED Details -->
                    <div v-if="currentStep === 6">
                        <h3 class="mb-6 text-lg font-extrabold text-slate-900 dark:text-white uppercase tracking-tight">Additional Details for CHED</h3>
                        <div class="grid gap-6 md:grid-cols-3">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5">
                                <Label class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-300">Indigenous Peoples (IP) Member? <span class="text-red-500">*</span></Label>
                                <select v-model="profileForm.ipMember" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950">
                                    <option :value="true">Yes</option>
                                    <option :value="false">No</option>
                                </select>
                                <div v-if="profileForm.ipMember" class="mt-4">
                                    <Label class="mb-1.5 block text-xs">Specify Tribe <span class="text-red-500">*</span></Label>
                                    <Input v-model="profileForm.ipMemberTribe" />
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5">
                                <Label class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-300">PWD Member? <span class="text-red-500">*</span></Label>
                                <select v-model="profileForm.pwdMember" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950">
                                    <option :value="true">Yes</option>
                                    <option :value="false">No</option>
                                </select>
                                <div v-if="profileForm.pwdMember" class="mt-4 space-y-4">
                                    <div><Label class="mb-1.5 block text-xs">PWD ID Number <span class="text-red-500">*</span></Label><Input v-model="profileForm.pwdMemberId" /></div>
                                    <div><Label class="mb-1.5 block text-xs">PWD Category <span class="text-red-500">*</span></Label><Input v-model="profileForm.pwdCategory" /></div>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5">
                                <Label class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-300">Solo Parent? <span class="text-red-500">*</span></Label>
                                <select v-model="profileForm.soloParent" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950">
                                    <option :value="true">Yes</option>
                                    <option :value="false">No</option>
                                </select>
                                <div v-if="profileForm.soloParent" class="mt-4">
                                    <Label class="mb-1.5 block text-xs">Solo Parent ID <span class="text-red-500">*</span></Label>
                                    <Input v-model="profileForm.soloParentId" />
                                </div>
                            </div>

                            <div class="col-span-full border-t border-slate-100 my-2 dark:border-white/5"></div>

                            <div><Label class="mb-1.5 block text-xs">Are you raised by a solo parent? : <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.raisedBySoloParent" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"><option value="">Please Select</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
                            <div><Label class="mb-1.5 block text-xs">Are you the first in your immediate family to attend college?: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.firstGenerationStudent" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"><option value="">Please Select</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
                            <div><Label class="mb-1.5 block text-xs">Student Category <span class="text-red-500">*</span></Label><select v-model="profileForm.studentCategory" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"><option value="">Please Select</option><option value="Old">Old</option><option value="New">New</option></select></div>
                            <div><Label class="mb-1.5 block text-xs">Family Size <span class="text-red-500">*</span></Label><Input v-model="profileForm.familySize" type="number" /></div>
                            <div><Label class="mb-1.5 block text-xs">Living in GIDA? <span class="text-red-500">*</span></Label><select v-model="profileForm.isGida" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">Please Select</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
                            <div><Label class="mb-1.5 block text-xs">Please specify (GIDA) <span class="text-red-500">*</span></Label><Input v-model="profileForm.descGida" /></div>
                            <div><Label class="mb-1.5 block text-xs">Belong to farmer/fisherfolk family? <span class="text-red-500">*</span></Label><select v-model="profileForm.isBelongToFarmer" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">Please Select</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
                            <div><Label class="mb-1.5 block text-xs">Belong to rebel returnees family? <span class="text-red-500">*</span></Label><select v-model="profileForm.isRebelReturnee" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">Please Select</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
                            <div><Label class="mb-1.5 block text-xs">Last school attended type <span class="text-red-500">*</span></Label><select v-model="profileForm.lastSchoolAttendedType" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"><option value="">Please Select</option><option :value="1">Private</option><option :value="2">Public</option></select></div>
                            
                            <div class="md:col-span-3"><Label class="mb-1.5 block text-xs">Student Type <span class="text-red-500">*</span></Label><select v-model="profileForm.studentType" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"><option value="">Please Select</option><option value="Senior HS Graduate">Senior HS Graduate</option><option value="High School Graduate (Old Curriculum)">High School Graduate (Old Curriculum)</option><option value="Alternative Delivery Mode (Home School, IMPACT, MISOSA, Night High School, Open High School)">Alternative Delivery Mode (Home School, IMPACT, MISOSA, Night High School, Open High School)</option><option value="Alternative Learning System (ALS) Passer">Alternative Learning System (ALS) Passer</option><option value="Transferee">Transferee</option><option value="Second Courser (Completed Degree in other school)">Second Courser (Completed Degree in other school)</option></select></div>
                            <div class="md:col-span-3"><Label class="mb-1.5 block text-xs">Senior High School Strand and Track: <span class="text-red-500"><sup>* required</sup></span></Label><select v-model="profileForm.shsTrack" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"><option value="">Please Select</option><option value="N/A">N/A</option><option value="Academic - STEM (Science, Technology, Engineering, and Mathematics)">Academic - STEM (Science, Technology, Engineering, and Mathematics)</option><option value="Academic - ABM (Accountancy, Business, and Management)">Academic - ABM (Accountancy, Business, and Management)</option><option value="Academic - HUMSS (Humanities and Social Sciences)">Academic - HUMSS (Humanities and Social Sciences)</option><option value="Academic - GAS (General Academic Strand)">Academic - GAS (General Academic Strand)</option><option value="TVL - ICT (Information and Communication Technology)">TVL - ICT (Information and Communication Technology)</option><option value="TVL - HE (Home Economics)">TVL - HE (Home Economics)</option><option value="TVL - IA (Industrial Arts)">TVL - IA (Industrial Arts)</option><option value="TVL - Agri-Fishery Arts">TVL - Agri-Fishery Arts</option><option value="Arts and Design">Arts and Design</option><option value="Sports Track">Sports Track</option></select></div>
                            
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5 md:col-span-1">
                                <Label class="mb-1.5 block text-xs">ADM Student? <span class="text-red-500">*</span></Label>
                                <select v-model="profileForm.isAdm" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"><option value="">Please Select</option><option value="Yes">Yes</option><option value="No">No</option></select>
                                <div v-if="profileForm.isAdm === 'Yes'" class="mt-4 space-y-4">
                                    <div><Label class="mb-1.5 block text-xs">School Name <span class="text-red-500">*</span></Label><Input v-model="profileForm.admSchool" /></div>
                                    <div><Label class="mb-1.5 block text-xs">SY Attended <span class="text-red-500">*</span></Label><Input v-model="profileForm.admSchoolYear" /></div>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5 md:col-span-1">
                                <Label class="mb-1.5 block text-xs">ALS Student? <span class="text-red-500">*</span></Label>
                                <select v-model="profileForm.isAls" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"><option value="">Please Select</option><option value="Yes">Yes</option><option value="No">No</option></select>
                                <div v-if="profileForm.isAls === 'Yes'" class="mt-4 space-y-4">
                                    <div><Label class="mb-1.5 block text-xs">School Name <span class="text-red-500">*</span></Label><Input v-model="profileForm.alsSchool" /></div>
                                    <div><Label class="mb-1.5 block text-xs">SY Attended <span class="text-red-500">*</span></Label><Input v-model="profileForm.alsSchoolYear" /></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sticky Footer for Actions -->
                <div class="sticky bottom-0 z-20 border-t border-slate-200 bg-white/95 px-5 py-4 backdrop-blur-md sm:px-8 sm:py-5 dark:border-white/10 dark:bg-slate-950/95">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <div class="relative flex size-12 items-center justify-center rounded-full bg-slate-50 dark:bg-white/5">
                                <svg class="size-10 transform -rotate-90" viewBox="0 0 36 36">
                                    <path class="text-slate-200 dark:text-slate-800" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" />
                                    <path class="text-sky-500 transition-all duration-500" :stroke-dasharray="`${completeness}, 100`" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" />
                                </svg>
                                <span class="absolute text-[10px] font-bold text-slate-700 dark:text-slate-300">{{ completeness }}%</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Progress</span>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ completeness === 100 ? 'Ready to save!' : 'Keep going' }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <Button variant="outline" @click="editMode = false" class="h-10 px-5 font-bold">Exit</Button>
                            <Button variant="outline" :disabled="currentStep === 0" @click="previousStep" class="h-10 px-5 font-bold">Back</Button>
                            <Button v-if="currentStep < wizardSteps.length - 1" @click="nextStep" class="h-10 px-6 font-bold bg-slate-900 text-white hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">Next Step</Button>
                            <Button v-else :disabled="profileForm.processing" @click="saveProfile" class="h-10 px-8 font-bold bg-sky-600 text-white hover:bg-sky-700 shadow-lg shadow-sky-600/20">
                                {{ profileForm.processing ? 'Saving...' : 'Save Profile' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </section>
        </Transition>
    </div>

    <!-- Modals for Achievements & Trainings -->
    <Dialog :open="achievementModalOpen" @update:open="achievementModalOpen = $event">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ editingAchievement ? 'Edit Award' : 'Add New Award' }}</DialogTitle>
                <DialogDescription>Add details about your achievements and academic awards.</DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid gap-2"><Label>Award Title</Label><Input v-model="achievementForm.title" /></div>
                <div class="grid gap-2"><Label>Awarding Body / Organization</Label><Input v-model="achievementForm.awarder" /></div>
                <div class="grid gap-2"><Label>Date Received</Label><Input v-model="achievementForm.date_received" type="date" /></div>
                <div class="grid gap-2"><Label>Description (Optional)</Label><Input v-model="achievementForm.description" /></div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="achievementModalOpen = false">Cancel</Button>
                <Button :disabled="achievementForm.processing" @click="submitAchievement">{{ editingAchievement ? 'Update' : 'Save' }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="trainingModalOpen" @update:open="trainingModalOpen = $event">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ editingTraining ? 'Edit Training' : 'Add New Training' }}</DialogTitle>
                <DialogDescription>Record seminars, workshops, or training sessions you've attended.</DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid gap-2"><Label>Title / Seminar Name</Label><Input v-model="trainingForm.title" /></div>
                <div class="grid gap-2"><Label>Organizer</Label><Input v-model="trainingForm.organizer" /></div>
                <div class="grid gap-2"><Label>Start Date</Label><Input v-model="trainingForm.date_from" type="date" /></div>
                <div class="grid gap-2"><Label>Venue</Label><Input v-model="trainingForm.venue" /></div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="trainingModalOpen = false">Cancel</Button>
                <Button :disabled="trainingForm.processing" @click="submitTraining">{{ editingTraining ? 'Update' : 'Save' }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="deleteConfirmOpen" @update:open="deleteConfirmOpen = $event">
        <DialogContent class="sm:max-w-[400px]">
            <DialogHeader>
                <DialogTitle>Delete Confirmation</DialogTitle>
                <DialogDescription>Are you sure you want to delete "{{ itemToDelete?.title }}"? This action cannot be undone.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteConfirmOpen = false">Cancel</Button>
                <Button variant="destructive" :disabled="achievementForm.processing || trainingForm.processing" @click="executeDelete">Delete</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <style>
    .fade-transform-enter-active,
    .fade-transform-leave-active {
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .fade-transform-enter-from {
        opacity: 0;
        transform: translateY(10px) scale(0.98);
    }

    .fade-transform-leave-to {
        opacity: 0;
        transform: translateY(-10px) scale(1.02);
    }

    /* Focus Mode Background Dimming */
    .dark .flex-1:has(.shadow-2xl) {
        background-color: rgba(2, 6, 23, 0.4);
        transition: background-color 0.5s ease;
    }
    </style>
</template>
