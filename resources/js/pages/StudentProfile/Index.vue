<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
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
                onSuccess: () => {
                    achievementModalOpen.value = false;
                    achievementForm.reset();
                },
            },
        );
    } else {
        achievementForm.post(achievementsRoutes.store.url(), {
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
                onSuccess: () => {
                    trainingModalOpen.value = false;
                    trainingForm.reset();
                },
            },
        );
    } else {
        trainingForm.post(trainingsRoutes.store.url(), {
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
                onSuccess: () => {
                    deleteConfirmOpen.value = false;
                    itemToDelete.value = null;
                },
            },
        );
    }
};
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

        <template v-else-if="profile.data">
            <section
                class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 md:flex-row md:items-center md:justify-between dark:border-white/10"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-sm font-bold text-slate-800 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            {{ getInitials(profile.data.firstName) }}
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
        </template>
    </div>

    <Dialog v-model:open="achievementModalOpen">
        <DialogContent
            class="rounded-lg border-slate-200 bg-white text-slate-950 sm:max-w-[450px] dark:border-white/10 dark:bg-slate-950 dark:text-white"
        >
            <DialogHeader>
                <DialogTitle>{{
                    editingAchievement ? 'Edit Achievement' : 'New Achievement'
                }}</DialogTitle>
                <DialogDescription>
                    Add your academic or professional awards.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4 pt-4" @submit.prevent="submitAchievement">
                <div class="grid gap-2">
                    <Label for="title">Title</Label>
                    <Input
                        id="title"
                        v-model="achievementForm.title"
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="awarder">Awarding Body</Label>
                    <Input id="awarder" v-model="achievementForm.awarder" />
                </div>
                <div class="grid gap-2">
                    <Label for="date_received">Date</Label>
                    <Input
                        id="date_received"
                        v-model="achievementForm.date_received"
                        type="date"
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <Input
                        id="description"
                        v-model="achievementForm.description"
                    />
                </div>
                <DialogFooter>
                    <Button
                        type="submit"
                        class="w-full rounded-md"
                        :disabled="achievementForm.processing"
                    >
                        {{ editingAchievement ? 'Update' : 'Save' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="trainingModalOpen">
        <DialogContent
            class="rounded-lg border-slate-200 bg-white text-slate-950 sm:max-w-[450px] dark:border-white/10 dark:bg-slate-950 dark:text-white"
        >
            <DialogHeader>
                <DialogTitle>{{
                    editingTraining ? 'Edit Record' : 'New Training'
                }}</DialogTitle>
                <DialogDescription>
                    Record your seminars or workshop attendance.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4 pt-4" @submit.prevent="submitTraining">
                <div class="grid gap-2">
                    <Label for="t-title">Title</Label>
                    <Input id="t-title" v-model="trainingForm.title" required />
                </div>
                <div class="grid gap-2">
                    <Label for="organizer">Organizer</Label>
                    <Input id="organizer" v-model="trainingForm.organizer" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="date_from">Start Date</Label>
                        <Input
                            id="date_from"
                            v-model="trainingForm.date_from"
                            type="date"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="date_to">End Date</Label>
                        <Input
                            id="date_to"
                            v-model="trainingForm.date_to"
                            type="date"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="submit"
                        class="w-full rounded-md"
                        :disabled="trainingForm.processing"
                    >
                        {{ editingTraining ? 'Update' : 'Save' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteConfirmOpen">
        <DialogContent
            class="rounded-lg border-slate-200 bg-white p-6 text-center text-slate-950 sm:max-w-[350px] dark:border-white/10 dark:bg-slate-950 dark:text-white"
        >
            <div
                class="mx-auto mb-4 flex size-11 items-center justify-center rounded-lg bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"
            >
                <Trash2 class="size-5" />
            </div>
            <DialogHeader>
                <DialogTitle class="text-center">Delete Record?</DialogTitle>
                <DialogDescription class="text-center">
                    This will permanently remove
                    <span class="font-bold text-foreground">
                        "{{ itemToDelete?.title }}"
                    </span>
                    .
                </DialogDescription>
            </DialogHeader>
            <div class="mt-6 flex flex-col gap-2">
                <Button
                    variant="destructive"
                    class="h-10 rounded-md"
                    @click="executeDelete"
                >
                    Delete
                </Button>
                <Button
                    variant="ghost"
                    class="h-10 rounded-md"
                    @click="deleteConfirmOpen = false"
                >
                    Cancel
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
