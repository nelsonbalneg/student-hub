<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    MoreHorizontal,
    Plus,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { index as userManagementIndex } from '@/routes/user-management';
import {
    destroy as destroyUserRoute,
    store as storeUserRoute,
    tagOffice as tagOfficeUserRoute,
    toggle as toggleUserRoute,
    update as updateUserRoute,
} from '@/routes/user-management/users';
import { update as updateUserRolesRoute } from '@/routes/user-management/users/roles';

type PageLink = { url: string | null; label: string; active: boolean };
type PageMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
};
type Page<T> = { data: T[]; links: PageLink[]; meta: PageMeta };
type Role = { id: number; name: string };
type TableQuery = Record<string, string | number>;
type FlashPageProps = {
    flash?: {
        toast?: {
            type?: 'success' | 'info' | 'warning' | 'error';
            message?: string;
        };
    };
};
type ManagedUser = {
    id: number;
    name: string;
    email: string;
    user_type?: string | null;
    office?: string | null;
    department?: string | null;
    status: string;
    is_active: boolean;
    roles: string[];
    office_id?: number | null;
    office_details?: { id: number; name: string; code: string | null } | null;
    created_at?: string;
};

const props = defineProps<{
    users: Page<ManagedUser>;
    filters: Record<string, string | undefined>;
    filterOptions: {
        roles: string[];
        offices: string[];
        departments: string[];
        userTypes: string[];
    };
    pageSizeOptions: number[];
    allRoles: Role[];
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
        assignRole: boolean;
    };
    lookupOffices: { id: number; name: string; code: string | null }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'User Management',
                href: '/user-management',
            },
        ],
    },
});

const search = ref(props.filters.user_search ?? '');
const perPage = ref(
    Number(props.filters.per_page ?? props.users.meta.per_page),
);
const filterOpen = ref(false);
const filters = ref({
    status: props.filters.status ?? '',
    role: props.filters.role ?? '',
    user_type: props.filters.user_type ?? '',
    office: props.filters.office ?? '',
    department: props.filters.department ?? '',
});
const selectedIds = ref<number[]>([]);
const menuUser = ref<ManagedUser | null>(null);
const forceDeleteAvailable = ref(false);
const modal = ref<
    | null
    | { type: 'view'; user: ManagedUser }
    | { type: 'create' }
    | { type: 'edit'; user: ManagedUser }
    | { type: 'assign-role'; user: ManagedUser }
    | { type: 'assign-office'; user: ManagedUser }
    | { type: 'delete'; user: ManagedUser }
>(null);

const userForm = useForm({
    name: '',
    email: '',
    password: '',
    is_active: true,
    user_type: '',
    office: '',
    department: '',
    office_id: null as number | null,
    roles: [] as string[],
});

const activeFilterCount = computed(
    () => Object.values(filters.value).filter(Boolean).length,
);
const allChecked = computed(
    () =>
        props.users.data.length > 0 &&
        props.users.data.every((user) => selectedIds.value.includes(user.id)),
);
const someChecked = computed(
    () => selectedIds.value.length > 0 && !allChecked.value,
);

const tableQuery = (): TableQuery =>
    Object.fromEntries(
        Object.entries({
            user_search: search.value,
            status: filters.value.status,
            role: filters.value.role,
            user_type: filters.value.user_type,
            office: filters.value.office,
            department: filters.value.department,
            per_page: perPage.value,
        }).filter(([, value]) => value !== ''),
    ) as TableQuery;

const applyFilters = () => {
    router.get(userManagementIndex.url(), tableQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
watch(filters, applyFilters, { deep: true });
watch(perPage, applyFilters);
watch(
    () => props.users.data,
    () => {
        selectedIds.value = [];
        menuUser.value = null;
    },
);

const clearFilters = () => {
    search.value = '';
    filters.value = {
        status: '',
        role: '',
        user_type: '',
        office: '',
        department: '',
    };
};

const toggleAll = () => {
    selectedIds.value = allChecked.value
        ? []
        : props.users.data.map((user) => user.id);
};

const toggleOne = (id: number) => {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((selectedId) => selectedId !== id)
        : [...selectedIds.value, id];
};

const openCreate = () => {
    userForm.reset();
    userForm.clearErrors();
    userForm.is_active = true;
    modal.value = { type: 'create' };
};

const openEdit = (user: ManagedUser) => {
    userForm.clearErrors();
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.password = '';
    userForm.is_active = user.is_active;
    userForm.user_type = user.user_type ?? '';
    userForm.office = user.office ?? '';
    userForm.department = user.department ?? '';
    userForm.office_id = user.office_id ?? null;
    userForm.roles = [...user.roles];
    modal.value = { type: 'edit', user };
};

const openAssignRole = (user: ManagedUser) => {
    userForm.clearErrors();
    userForm.roles = [...user.roles];
    modal.value = { type: 'assign-role', user };
};

const saveUser = () => {
    if (modal.value?.type === 'edit') {
        userForm.patch(updateUserRoute.url(modal.value.user.id), {
            preserveScroll: true,
            onSuccess: () => (modal.value = null),
        });

        return;
    }

    userForm.post(storeUserRoute.url(), {
        preserveScroll: true,
        onSuccess: () => (modal.value = null),
    });
};

const saveAssignedRoles = () => {
    if (modal.value?.type !== 'assign-role') {
        return;
    }

    userForm.patch(updateUserRolesRoute.url(modal.value.user.id), {
        preserveScroll: true,
        onSuccess: () => (modal.value = null),
    });
};

const openAssignOffice = (user: ManagedUser) => {
    userForm.clearErrors();
    userForm.office_id = user.office_id ?? null;
    modal.value = { type: 'assign-office', user };
};

const saveAssignedOffice = () => {
    if (modal.value?.type !== 'assign-office') {
        return;
    }

    userForm.patch(tagOfficeUserRoute.url(modal.value.user.id), {
        preserveScroll: true,
        onSuccess: () => (modal.value = null),
    });
};

const openDelete = (user: ManagedUser) => {
    forceDeleteAvailable.value = false;
    modal.value = { type: 'delete', user };
};

const confirmDelete = (force = false) => {
    if (modal.value?.type !== 'delete') {
        return;
    }

    router.delete(
        destroyUserRoute.url(
            modal.value.user.id,
            force ? { query: { force: 1 } } : undefined,
        ),
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const props = page.props as FlashPageProps;
                const toastType = props.flash?.toast?.type;

                if (toastType === 'success') {
                    modal.value = null;
                    forceDeleteAvailable.value = false;

                    return;
                }

                if (toastType === 'error' && !force) {
                    forceDeleteAvailable.value = true;
                    toast.warning('Force delete is available for this user.');
                }
            },
        },
    );
};

const toggleUserStatus = (user: ManagedUser) => {
    router.patch(toggleUserRoute.url(user.id), {}, { preserveScroll: true });
};

const navigatePage = (url: string | null) => {
    if (!url) {
        return;
    }

    router.get(url, {}, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="User Management" />

    <div class="flex h-full flex-1 flex-col gap-3 overflow-x-auto p-3">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-base font-bold text-slate-800 dark:text-white">
                    User Management
                </h1>
                <p class="text-xs text-slate-400">
                    Manage system users, roles, and permissions.
                </p>
            </div>
            <Button
                v-if="can.create"
                class="h-8 gap-1.5 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-700"
                @click="openCreate"
            >
                <Plus class="h-3.5 w-3.5" />
                Add User
            </Button>
        </div>

        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <Search
                    class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email..."
                    class="h-8 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-8 text-xs text-slate-900 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                />
                <button
                    v-if="search"
                    class="absolute top-1/2 right-2 -translate-y-1/2 text-slate-400"
                    @click="search = ''"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
            <button
                :class="[
                    'inline-flex h-8 items-center gap-1.5 rounded-lg border px-2.5 text-xs font-medium transition-colors',
                    filterOpen || activeFilterCount > 0
                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-white/[0.05]',
                ]"
                @click="filterOpen = !filterOpen"
            >
                <SlidersHorizontal class="h-3.5 w-3.5" />
                Filters
                <span
                    v-if="activeFilterCount > 0"
                    class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-600 text-[9px] font-bold text-white"
                    >{{ activeFilterCount }}</span
                >
            </button>
            <button
                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-white/[0.05]"
                @click="clearFilters"
            >
                <RefreshCw class="h-3.5 w-3.5" />
                Reset
            </button>
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-2 opacity-0"
        >
            <div
                v-if="filterOpen"
                class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
            >
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <label
                        class="grid gap-1 text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        Status
                        <select v-model="filters.status" class="filter-select">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </label>
                    <label
                        class="grid gap-1 text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        Role
                        <select v-model="filters.role" class="filter-select">
                            <option value="">All</option>
                            <option
                                v-for="role in filterOptions.roles"
                                :key="role"
                                :value="role"
                            >
                                {{ role }}
                            </option>
                        </select>
                    </label>
                    <label
                        class="grid gap-1 text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        User Type
                        <select
                            v-model="filters.user_type"
                            class="filter-select"
                        >
                            <option value="">All</option>
                            <option
                                v-for="type in filterOptions.userTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ type }}
                            </option>
                        </select>
                    </label>
                    <label
                        class="grid gap-1 text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        Office
                        <select v-model="filters.office" class="filter-select">
                            <option value="">All</option>
                            <option
                                v-for="office in filterOptions.offices"
                                :key="office"
                                :value="office"
                            >
                                {{ office }}
                            </option>
                        </select>
                    </label>
                    <label
                        class="grid gap-1 text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        Department
                        <select
                            v-model="filters.department"
                            class="filter-select"
                        >
                            <option value="">All</option>
                            <option
                                v-for="department in filterOptions.departments"
                                :key="department"
                                :value="department"
                            >
                                {{ department }}
                            </option>
                        </select>
                    </label>
                </div>
            </div>
        </Transition>

        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Showing
                    <span
                        class="font-semibold text-slate-700 dark:text-slate-200"
                    >
                        {{ users.meta.from ?? 0 }}-{{ users.meta.to ?? 0 }}
                    </span>
                    of
                    <span
                        class="font-semibold text-slate-700 dark:text-slate-200"
                    >
                        {{ users.meta.total }}
                    </span>
                    users
                </p>
                <label
                    class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400"
                >
                    Rows
                    <select v-model.number="perPage" class="rows-select">
                        <option
                            v-for="size in pageSizeOptions"
                            :key="size"
                            :value="size"
                        >
                            {{ size }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10"
                >
                    <thead class="bg-slate-50/80 dark:bg-white/[0.03]">
                        <tr>
                            <th class="w-8 px-3 py-2">
                                <input
                                    type="checkbox"
                                    class="h-3.5 w-3.5 accent-emerald-600"
                                    :checked="allChecked"
                                    :indeterminate="someChecked"
                                    @change="toggleAll"
                                />
                            </th>
                            <th class="table-head">Name</th>
                            <th class="table-head">Email</th>
                            <th class="table-head">Type</th>
                            <th class="table-head">Office</th>
                            <th class="table-head">Role</th>
                            <th class="table-head">Status</th>
                            <th class="table-head">Created</th>
                            <th class="table-head text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="group transition-colors hover:bg-slate-50/70 dark:hover:bg-white/[0.03]"
                            :class="
                                selectedIds.includes(user.id)
                                    ? 'bg-emerald-50/40 dark:bg-emerald-500/5'
                                    : ''
                            "
                        >
                            <td class="px-3 py-2">
                                <input
                                    type="checkbox"
                                    class="h-3.5 w-3.5 accent-emerald-600"
                                    :checked="selectedIds.includes(user.id)"
                                    @change="toggleOne(user.id)"
                                />
                            </td>
                            <td class="px-3 py-2">
                                <button
                                    class="flex items-center gap-2 text-left"
                                    @click="modal = { type: 'view', user }"
                                >
                                    <div
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-sky-500 text-[11px] font-bold text-white"
                                    >
                                        {{ user.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ user.name }}
                                        </p>
                                        <p class="text-[10px] text-slate-400">
                                            #{{ user.id }}
                                        </p>
                                    </div>
                                </button>
                            </td>
                            <td
                                class="px-3 py-2 text-xs text-slate-600 dark:text-slate-300"
                            >
                                {{ user.email }}
                            </td>
                            <td
                                class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ user.user_type || 'Member' }}
                            </td>
                            <td
                                class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400"
                            >
                                <span
                                    v-if="user.office_details"
                                    class="font-bold text-emerald-600"
                                >
                                    {{
                                        user.office_details.code ||
                                        user.office_details.name
                                    }}
                                </span>
                                <span v-else>
                                    {{ user.office || user.department || '-' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    v-if="user.roles[0]"
                                    class="inline-flex items-center rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300"
                                >
                                    {{ user.roles[0] }}
                                </span>
                                <span v-else class="text-[10px] text-slate-400"
                                    >-</span
                                >
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                    :class="
                                        user.is_active
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-500 dark:bg-white/[0.06] dark:text-slate-300'
                                    "
                                >
                                    {{ user.status }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-[10px] text-slate-500">
                                {{ user.created_at || '-' }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <div class="relative inline-flex">
                                    <button
                                        class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/[0.06] dark:hover:text-white"
                                        @click="
                                            menuUser =
                                                menuUser?.id === user.id
                                                    ? null
                                                    : user
                                        "
                                    >
                                        <MoreHorizontal class="h-4 w-4" />
                                    </button>
                                    <div
                                        v-if="menuUser?.id === user.id"
                                        class="absolute top-8 right-0 z-10 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-left text-xs shadow-xl dark:border-white/10 dark:bg-slate-900"
                                    >
                                        <button
                                            class="menu-item"
                                            @click="
                                                modal = { type: 'view', user };
                                                menuUser = null;
                                            "
                                        >
                                            View Details
                                        </button>
                                        <button
                                            v-if="can.update"
                                            class="menu-item"
                                            @click="
                                                openEdit(user);
                                                menuUser = null;
                                            "
                                        >
                                            Edit User
                                        </button>
                                        <button
                                            v-if="can.assignRole"
                                            class="menu-item"
                                            @click="
                                                openAssignRole(user);
                                                menuUser = null;
                                            "
                                        >
                                            Assign Role
                                        </button>
                                        <button
                                            v-if="can.update"
                                            class="menu-item"
                                            @click="
                                                openAssignOffice(user);
                                                menuUser = null;
                                            "
                                        >
                                            Tag Office
                                        </button>
                                        <button
                                            v-if="can.update"
                                            class="menu-item"
                                            @click="
                                                toggleUserStatus(user);
                                                menuUser = null;
                                            "
                                        >
                                            {{
                                                user.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate'
                                            }}
                                        </button>
                                        <button
                                            v-if="can.delete"
                                            class="menu-item text-red-500"
                                            @click="
                                                openDelete(user);
                                                menuUser = null;
                                            "
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td
                                colspan="9"
                                class="py-16 text-center text-slate-400"
                            >
                                <div
                                    class="mx-auto flex flex-col items-center gap-3"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-white/[0.06]"
                                    >
                                        <Search
                                            class="h-6 w-6 text-slate-400"
                                        />
                                    </div>
                                    <p class="text-sm font-medium">
                                        No users found
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        Try adjusting your search or filters.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="users.meta.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 dark:border-white/10"
            >
                <p class="text-xs text-slate-500">
                    Page {{ users.meta.current_page }} of
                    {{ users.meta.last_page }}
                </p>
                <div class="flex items-center gap-1">
                    <button
                        class="pagination-btn"
                        :disabled="!users.links[0]?.url"
                        @click="navigatePage(users.links[0]?.url)"
                    >
                        <ChevronsLeft class="h-3.5 w-3.5" />
                    </button>
                    <button
                        class="pagination-btn"
                        :disabled="users.meta.current_page === 1"
                        @click="
                            navigatePage(
                                users.links[users.meta.current_page - 1]?.url,
                            )
                        "
                    >
                        <ChevronLeft class="h-3.5 w-3.5" />
                    </button>
                    <template
                        v-for="link in users.links.slice(1, -1)"
                        :key="link.label"
                    >
                        <button
                            v-if="link.label !== '...'"
                            :class="[
                                'inline-flex h-7 w-7 items-center justify-center rounded-lg text-xs font-medium transition-colors',
                                link.active
                                    ? 'bg-emerald-600 text-white'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/[0.06]',
                            ]"
                            @click="navigatePage(link.url)"
                            v-html="link.label"
                        />
                        <span v-else class="px-1 text-slate-400">...</span>
                    </template>
                    <button
                        class="pagination-btn"
                        :disabled="
                            users.meta.current_page === users.meta.last_page
                        "
                        @click="
                            navigatePage(
                                users.links[users.meta.current_page + 1]?.url,
                            )
                        "
                    >
                        <ChevronRight class="h-3.5 w-3.5" />
                    </button>
                    <button
                        class="pagination-btn"
                        :disabled="!users.links[users.links.length - 1]?.url"
                        @click="
                            navigatePage(
                                users.links[users.links.length - 1]?.url,
                            )
                        "
                    >
                        <ChevronsRight class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div
        v-if="modal"
        class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
        @click.self="modal = null"
    >
        <div
            class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl dark:bg-slate-950"
        >
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                    {{
                        modal.type === 'delete'
                            ? 'Delete User'
                            : modal.type === 'assign-role'
                              ? 'Assign Role'
                              : modal.type === 'create'
                                ? 'Create User'
                                : modal.type === 'edit'
                                  ? 'Edit User'
                                  : modal.type === 'assign-office'
                                    ? 'Assign Office'
                                    : 'User Details'
                    }}
                </h2>
                <Button variant="ghost" size="sm" @click="modal = null"
                    >Close</Button
                >
            </div>

            <div
                v-if="modal.type === 'view'"
                class="grid gap-3 text-sm text-slate-600 dark:text-slate-300"
            >
                <p><strong>Name:</strong> {{ modal.user.name }}</p>
                <p><strong>Email:</strong> {{ modal.user.email }}</p>
                <p>
                    <strong>User type:</strong>
                    {{ modal.user.user_type || 'Member' }}
                </p>
                <p>
                    <strong>Office:</strong> {{ modal.user.office || 'None' }}
                </p>
                <p>
                    <strong>Department:</strong>
                    {{ modal.user.department || 'None' }}
                </p>
                <p>
                    <strong>Roles:</strong>
                    {{ modal.user.roles.join(', ') || 'No role' }}
                </p>
            </div>

            <div v-if="modal.type === 'assign-office'" class="grid gap-4">
                <p class="text-xs text-slate-500">
                    Assign
                    <span class="font-bold text-slate-900 dark:text-white">{{
                        modal.user.name
                    }}</span>
                    to an administrative office for clearance processing.
                </p>
                <div class="grid gap-3">
                    <label
                        class="text-[10px] font-bold text-slate-400 uppercase"
                        >Select Office</label
                    >
                    <select
                        v-model="userForm.office_id"
                        class="form-input bg-white"
                    >
                        <option :value="null">No Office (Unset)</option>
                        <option
                            v-for="office in lookupOffices"
                            :key="office.id"
                            :value="office.id"
                        >
                            {{ office.name }}
                            <span v-if="office.code">({{ office.code }})</span>
                        </option>
                    </select>
                    <InputError :message="userForm.errors.office_id" />
                </div>
                <div class="flex justify-end pt-4">
                    <Button
                        class="bg-emerald-600 text-white hover:bg-emerald-700"
                        @click="saveAssignedOffice"
                        :disabled="userForm.processing"
                    >
                        {{
                            userForm.processing
                                ? 'Saving...'
                                : 'Save Office Assignment'
                        }}
                    </Button>
                </div>
            </div>

            <form
                v-else-if="modal.type === 'create' || modal.type === 'edit'"
                class="grid gap-4"
                @submit.prevent="saveUser"
            >
                <input
                    v-model="userForm.name"
                    class="form-input"
                    placeholder="Name"
                />
                <InputError :message="userForm.errors.name" />
                <input
                    v-model="userForm.email"
                    class="form-input"
                    placeholder="Email"
                    type="email"
                />
                <InputError :message="userForm.errors.email" />
                <input
                    v-model="userForm.password"
                    class="form-input"
                    :placeholder="
                        modal.type === 'edit'
                            ? 'New password (optional)'
                            : 'Password'
                    "
                    type="password"
                />
                <InputError :message="userForm.errors.password" />
                <div class="grid gap-3 md:grid-cols-3">
                    <input
                        v-model="userForm.user_type"
                        class="form-input"
                        placeholder="User type"
                    />
                    <input
                        v-model="userForm.office"
                        class="form-input"
                        placeholder="Office"
                    />
                    <input
                        v-model="userForm.department"
                        class="form-input"
                        placeholder="Department"
                    />
                </div>
                <label
                    class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"
                >
                    <input
                        v-model="userForm.is_active"
                        type="checkbox"
                        class="size-4 accent-emerald-600"
                    />
                    Active account
                </label>
                <div class="grid gap-2 md:grid-cols-2">
                    <label
                        v-for="role in allRoles"
                        :key="role.id"
                        class="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-white/10"
                    >
                        <input
                            v-model="userForm.roles"
                            type="checkbox"
                            class="size-4 accent-emerald-600"
                            :value="role.name"
                        />
                        {{ role.name }}
                    </label>
                </div>
                <Button type="submit" :disabled="userForm.processing">
                    Save User
                </Button>
            </form>

            <form
                v-else-if="modal.type === 'assign-role'"
                class="grid gap-4"
                @submit.prevent="saveAssignedRoles"
            >
                <div class="grid gap-2 md:grid-cols-2">
                    <label
                        v-for="role in allRoles"
                        :key="role.id"
                        class="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-white/10"
                    >
                        <input
                            v-model="userForm.roles"
                            type="checkbox"
                            class="size-4 accent-emerald-600"
                            :value="role.name"
                        />
                        {{ role.name }}
                    </label>
                </div>
                <Button type="submit" :disabled="userForm.processing">
                    Assign Roles
                </Button>
            </form>

            <div v-else class="space-y-4">
                <p class="text-sm text-slate-500">
                    Delete <strong>{{ modal.user.name }}</strong
                    >? This action cannot be undone.
                </p>
                <p
                    v-if="forceDeleteAvailable"
                    class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs font-medium text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
                >
                    This user is linked to existing records. Force delete will
                    permanently remove or unlink records that block deleting
                    this account.
                </p>
                <div class="flex justify-end gap-2">
                    <Button variant="secondary" @click="modal = null"
                        >Cancel</Button
                    >
                    <Button variant="destructive" @click="confirmDelete()">
                        <Trash2 class="size-4" />
                        Delete
                    </Button>
                    <Button
                        v-if="forceDeleteAvailable"
                        class="bg-red-700 text-white hover:bg-red-800"
                        @click="confirmDelete(true)"
                    >
                        <Trash2 class="size-4" />
                        Force Delete
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.filter-select {
    @apply h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-emerald-400 focus:outline-none;
    color-scheme: light;
    background-color: #ffffff;
    color: #334155;
}

.rows-select {
    @apply h-8 w-20 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-emerald-400 focus:outline-none;
    color-scheme: light;
    background-color: #ffffff;
    color: #334155;
}

.filter-select option {
    @apply bg-white text-slate-700;
    background-color: #ffffff;
    color: #334155;
}

.table-head {
    @apply px-3 py-2 text-left text-[10px] font-bold tracking-wide text-slate-500 uppercase;
}

.pagination-btn {
    @apply inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:bg-white/[0.06];
}

.menu-item {
    @apply block w-full px-3 py-2 text-left text-slate-700 hover:bg-slate-50;
    color: #334155;
    opacity: 1;
}

.form-input {
    @apply h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-400 focus:outline-none;
    color-scheme: light;
    background-color: #ffffff;
    color: #0f172a;
}
</style>

<style>
.dark .filter-select,
.dark .rows-select,
.dark .form-input {
    color-scheme: dark;
}

.dark .filter-select,
.dark .rows-select {
    border-color: rgb(255 255 255 / 0.1);
    background-color: #020617;
    color: #f1f5f9;
}

.dark .filter-select option,
.dark .rows-select option {
    background-color: #020617;
    color: #f1f5f9;
}

.dark .form-input {
    border-color: rgb(255 255 255 / 0.1);
    background-color: #020617;
    color: #f1f5f9;
}

.dark .menu-item {
    color: #cbd5e1;
}

.dark .menu-item:hover {
    background-color: rgb(255 255 255 / 0.06);
}
</style>
