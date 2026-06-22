<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    Key,
    Pencil,
    Plus,
    Save,
    Search,
    Shield,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';

type Permission = {
    id: number;
    name: string;
    label: string;
    module: string;
    roles_count?: number;
};
type RolePermission = Pick<Permission, 'id' | 'name'>;
type Role = {
    id: number;
    selection_token: string;
    name: string;
    users_count?: number;
    permissions: RolePermission[];
};
type PermissionModuleGroup = {
    label: string;
    permissions: Permission[];
};

const props = defineProps<{
    roles: Role[];
    permissions: Permission[];
    selectedRoleId?: number | null;
    can: {
        createRole: boolean;
        updateRole: boolean;
        deleteRole: boolean;
        createPermission: boolean;
        updatePermission: boolean;
        deletePermission: boolean;
    };
}>();

const ACTIVE_ROLE_KEY = 'user_management_active_role_token';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Roles & Permissions',
                href: '/user-management/roles',
            },
        ],
    },
});

const activeRoleId = ref<number | null>(
    props.selectedRoleId ?? props.roles[0]?.id ?? null,
);
const activeRole = computed(
    () => props.roles.find((role) => role.id === activeRoleId.value) ?? null,
);
const checkedIds = ref<Set<number>>(
    new Set(
        activeRole.value?.permissions.map((permission) => permission.id) ?? [],
    ),
);
const expandedModules = ref<Set<string>>(new Set());
const permissionSearch = ref('');
const showRoleDrawer = ref(false);
const showPermissionDrawer = ref(false);
const editingRole = ref<Role | null>(null);
const editingPermission = ref<Permission | null>(null);
const deletingRole = ref<Role | null>(null);
const deletingPermission = ref<Permission | null>(null);

const roleForm = useForm({
    name: '',
    permissions: [] as string[],
});
const permissionForm = useForm({
    name: '',
    module: '',
});

onMounted(() => {
    const url = new URL(window.location.href);
    const activeRoleToken = activeRole.value?.selection_token;

    if (url.searchParams.has('roleId') && activeRoleToken) {
        url.searchParams.set('roleId', activeRoleToken);
        window.history.replaceState(window.history.state, '', url);
        window.localStorage.setItem(ACTIVE_ROLE_KEY, activeRoleToken);

        return;
    }

    const savedRoleToken = window.localStorage.getItem(ACTIVE_ROLE_KEY);

    if (savedRoleToken) {
        url.searchParams.set('roleId', savedRoleToken);
        window.location.replace(url);
    }
});

watch(activeRoleId, (roleId) => {
    if (roleId === null || typeof window === 'undefined') {
        return;
    }

    const roleToken = props.roles.find(
        (role) => role.id === roleId,
    )?.selection_token;

    if (!roleToken) {
        return;
    }

    window.localStorage.setItem(ACTIVE_ROLE_KEY, roleToken);

    const url = new URL(window.location.href);
    url.searchParams.set('roleId', roleToken);
    window.history.replaceState(window.history.state, '', url);
});

watch(activeRole, (role) => {
    checkedIds.value = new Set(
        role?.permissions.map((permission) => permission.id) ?? [],
    );
});

const moduleName = (permission: Permission): string =>
    permission.module || permission.name.split('.')[0] || 'General';

const moduleKey = (module: string): string =>
    module
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim() || 'general';

const moduleLabel = (module: string): string =>
    moduleKey(module)
        .split(' ')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

const moduleMap = computed(() =>
    props.permissions.reduce<Record<string, PermissionModuleGroup>>(
        (groups, permission) => {
            const module = moduleName(permission);
            const key = moduleKey(module);

            groups[key] ??= {
                label: moduleLabel(module),
                permissions: [],
            };
            groups[key].permissions.push(permission);

            return groups;
        },
        {},
    ),
);

const filteredModuleMap = computed(() => {
    if (!permissionSearch.value) {
        return moduleMap.value;
    }

    const query = permissionSearch.value.toLowerCase();

    return Object.entries(moduleMap.value).reduce<
        Record<string, PermissionModuleGroup>
    >((groups, [key, group]) => {
        const filteredPermissions = group.permissions.filter(
            (permission) =>
                permission.name.toLowerCase().includes(query) ||
                permission.label.toLowerCase().includes(query) ||
                moduleName(permission).toLowerCase().includes(query) ||
                group.label.toLowerCase().includes(query),
        );

        if (filteredPermissions.length > 0) {
            groups[key] = {
                ...group,
                permissions: filteredPermissions,
            };
        }

        return groups;
    }, {});
});

const allChecked = computed(
    () =>
        props.permissions.length > 0 &&
        props.permissions.every((permission) =>
            checkedIds.value.has(permission.id),
        ),
);
const anyChecked = computed(() =>
    props.permissions.some((permission) => checkedIds.value.has(permission.id)),
);
const systemRoles = ['Super Admin'];

const updateCheckedIds = (callback: (ids: Set<number>) => void) => {
    const nextIds = new Set(checkedIds.value);
    callback(nextIds);
    checkedIds.value = nextIds;
};

const toggleModule = (module: string) => {
    const nextModules = new Set(expandedModules.value);

    if (nextModules.has(module)) {
        nextModules.delete(module);
    } else {
        nextModules.add(module);
    }

    expandedModules.value = nextModules;
};

const moduleChecked = (module: string) =>
    moduleMap.value[module].permissions.every((permission) =>
        checkedIds.value.has(permission.id),
    );

const moduleIndeterminate = (module: string) => {
    const checkedCount = moduleMap.value[module].permissions.filter(
        (permission) => checkedIds.value.has(permission.id),
    ).length;

    return (
        checkedCount > 0 &&
        checkedCount < moduleMap.value[module].permissions.length
    );
};

const toggleModuleAll = (module: string) => {
    updateCheckedIds((ids) => {
        if (moduleChecked(module)) {
            moduleMap.value[module].permissions.forEach((permission) =>
                ids.delete(permission.id),
            );
        } else {
            moduleMap.value[module].permissions.forEach((permission) =>
                ids.add(permission.id),
            );
        }
    });
};

const toggleAll = () => {
    checkedIds.value = allChecked.value
        ? new Set()
        : new Set(props.permissions.map((permission) => permission.id));
};

const togglePermission = (permissionId: number) => {
    updateCheckedIds((ids) => {
        ids.has(permissionId)
            ? ids.delete(permissionId)
            : ids.add(permissionId);
    });
};

const savePermissions = () => {
    if (!activeRole.value) {
        return;
    }

    router.patch(
        `/user-management/roles/${activeRole.value.id}/permissions`,
        { permissions: [...checkedIds.value] },
        { preserveScroll: true, preserveState: true },
    );
};

const openCreateRole = () => {
    editingRole.value = null;
    roleForm.reset();
    roleForm.clearErrors();
    roleForm.permissions = [];
    showRoleDrawer.value = true;
};

const openEditRole = (role: Role) => {
    editingRole.value = role;
    roleForm.clearErrors();
    roleForm.name = role.name;
    roleForm.permissions = role.permissions.map(
        (permission) => permission.name,
    );
    showRoleDrawer.value = true;
};

const submitRole = () => {
    if (editingRole.value) {
        roleForm.patch(`/user-management/roles/${editingRole.value.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => (showRoleDrawer.value = false),
        });

        return;
    }

    roleForm.post('/user-management/roles', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (showRoleDrawer.value = false),
    });
};

const deleteRole = () => {
    if (!deletingRole.value) {
        return;
    }

    router.delete(`/user-management/roles/${deletingRole.value.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (deletingRole.value = null),
    });
};

const openCreatePermission = () => {
    editingPermission.value = null;
    permissionForm.reset();
    permissionForm.clearErrors();
    showPermissionDrawer.value = true;
};

const openEditPermission = (permission: Permission) => {
    editingPermission.value = permission;
    permissionForm.clearErrors();
    permissionForm.name = permission.name;
    permissionForm.module = permission.module;
    showPermissionDrawer.value = true;
};

const submitPermission = () => {
    if (editingPermission.value) {
        permissionForm.patch(
            `/user-management/permissions/${editingPermission.value.id}`,
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => (showPermissionDrawer.value = false),
            },
        );

        return;
    }

    permissionForm.post('/user-management/permissions', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (showPermissionDrawer.value = false),
    });
};

const deletePermission = () => {
    if (!deletingPermission.value) {
        return;
    }

    router.delete(
        `/user-management/permissions/${deletingPermission.value.id}`,
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => (deletingPermission.value = null),
        },
    );
};
</script>

<template>
    <Head title="Roles & Permissions" />

    <div
        class="flex h-[calc(100vh-3rem)] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white md:flex-row dark:border-white/10 dark:bg-slate-950"
    >
        <aside
            class="flex h-[35%] w-full shrink-0 flex-col border-b border-slate-100 md:h-full md:w-64 md:border-b-0 md:border-r dark:border-white/10"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5 dark:border-white/10"
            >
                <span
                    class="flex items-center gap-1.5 text-xs font-bold tracking-wide text-slate-700 uppercase dark:text-slate-200"
                >
                    <Shield class="h-3.5 w-3.5 text-emerald-600" />
                    Roles
                </span>
                <button
                    v-if="can.createRole"
                    class="flex h-6 w-6 items-center justify-center rounded-md bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300"
                    @click="openCreateRole"
                >
                    <Plus class="h-3 w-3" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto py-1">
                <button
                    v-for="role in roles"
                    :key="role.id"
                    class="group flex w-full items-center justify-between px-3 py-2 text-left text-xs transition-colors"
                    :class="
                        activeRoleId === role.id
                            ? 'bg-emerald-50 font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                            : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-white/[0.04]'
                    "
                    @click="activeRoleId = role.id"
                >
                    <div class="flex min-w-0 flex-col">
                        <span class="truncate">{{ role.name }}</span>
                        <span class="mt-0.5 text-[10px] text-slate-400">
                            {{ role.users_count ?? 0 }} users
                        </span>
                    </div>
                    <div
                        v-if="activeRoleId === role.id"
                        class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        <button
                            v-if="can.updateRole"
                            class="rounded p-0.5 hover:bg-emerald-100 dark:hover:bg-white/[0.06]"
                            @click.stop="openEditRole(role)"
                        >
                            <Pencil class="h-3 w-3 text-slate-500" />
                        </button>
                        <button
                            v-if="
                                can.deleteRole &&
                                !systemRoles.includes(role.name)
                            "
                            class="rounded p-0.5 hover:bg-red-50 dark:hover:bg-red-500/10"
                            @click.stop="deletingRole = role"
                        >
                            <Trash2 class="h-3 w-3 text-red-400" />
                        </button>
                    </div>
                </button>

                <div
                    v-if="roles.length === 0"
                    class="px-3 py-8 text-center text-xs text-slate-400"
                >
                    No roles available.
                </div>
            </div>
        </aside>

        <main class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <div
                class="flex flex-col gap-3 border-b border-slate-100 bg-white px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-slate-950"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <Key class="h-3.5 w-3.5 shrink-0 text-emerald-600" />
                    <span
                        class="text-xs font-bold tracking-wide text-slate-700 uppercase dark:text-slate-200"
                    >
                        Permissions
                    </span>
                    <span
                        v-if="activeRole"
                        class="truncate text-[10px] text-slate-400"
                    >
                        - {{ activeRole.name }} ({{ checkedIds.size }}/{{
                            permissions.length
                        }}
                        assigned)
                    </span>
                </div>
                <div class="flex w-full items-center gap-2 sm:w-auto">
                    <div class="relative flex-1 sm:flex-none">
                        <Search
                            class="absolute top-1/2 left-2 h-3 w-3 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="permissionSearch"
                            type="text"
                            placeholder="Search permissions..."
                            class="h-7 w-full rounded-lg border border-slate-200 bg-white pr-2 pl-7 text-[11px] focus:border-emerald-400 focus:outline-none sm:w-48 dark:border-white/10 dark:bg-slate-900"
                        />
                    </div>
                    <button
                        v-if="can.createPermission"
                        class="flex h-7 items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 text-[11px] text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/[0.05]"
                        @click="openCreatePermission"
                    >
                        <Plus class="h-3 w-3" />
                        Add Permission
                    </button>
                    <button
                        v-if="can.updateRole && activeRole"
                        class="flex h-7 items-center gap-1.5 rounded-lg bg-emerald-600 px-2.5 text-[11px] font-semibold text-white hover:bg-emerald-700"
                        @click="savePermissions"
                    >
                        <Save class="h-3 w-3" />
                        Save
                    </button>
                </div>
            </div>

            <label
                class="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-4 py-1.5 dark:border-white/10 dark:bg-white/[0.03]"
            >
                <input
                    type="checkbox"
                    :checked="allChecked"
                    :indeterminate="anyChecked && !allChecked"
                    class="h-3.5 w-3.5 accent-emerald-600"
                    @change="toggleAll"
                />
                <span
                    class="text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                >
                    Select All Permissions
                </span>
            </label>

            <div class="flex-1 overflow-y-auto">
                <div
                    v-for="(moduleGroup, module) in filteredModuleMap"
                    :key="module"
                    class="border-b border-slate-50 dark:border-white/5"
                >
                    <div
                        class="flex cursor-pointer items-center gap-2 bg-slate-50/70 px-4 py-1.5 hover:bg-slate-100/80 dark:bg-white/[0.02] dark:hover:bg-white/[0.05]"
                        @click="toggleModule(module)"
                    >
                        <component
                            :is="
                                expandedModules.has(module)
                                    ? ChevronDown
                                    : ChevronRight
                            "
                            class="h-3 w-3 text-slate-400"
                        />
                        <input
                            type="checkbox"
                            :checked="moduleChecked(module)"
                            :indeterminate="moduleIndeterminate(module)"
                            class="h-3.5 w-3.5 accent-emerald-600"
                            @click.stop
                            @change="toggleModuleAll(module)"
                        />
                        <span
                            class="text-[11px] font-bold tracking-wide text-slate-600 uppercase dark:text-slate-300"
                        >
                            {{ moduleGroup.label }}
                        </span>
                        <span class="ml-auto text-[10px] text-slate-400">
                            {{
                                moduleGroup.permissions.filter((permission) =>
                                    checkedIds.has(permission.id),
                                ).length
                            }}/{{ moduleGroup.permissions.length }}
                        </span>
                    </div>

                    <div
                        v-if="expandedModules.has(module)"
                        class="divide-y divide-slate-50 dark:divide-white/5"
                    >
                        <div
                            v-for="permission in moduleGroup.permissions"
                            :key="permission.id"
                            class="group flex items-center gap-3 px-6 py-1.5 transition-colors hover:bg-emerald-50/40 dark:hover:bg-emerald-500/5"
                        >
                            <input
                                :id="`permission-${permission.id}`"
                                type="checkbox"
                                :checked="checkedIds.has(permission.id)"
                                class="h-3.5 w-3.5 shrink-0 accent-emerald-600"
                                @change="togglePermission(permission.id)"
                            />
                            <label
                                :for="`permission-${permission.id}`"
                                class="flex-1 cursor-pointer"
                            >
                                <p
                                    class="text-[11px] font-medium text-slate-700 dark:text-slate-200"
                                >
                                    {{ permission.label }}
                                </p>
                                <p class="font-mono text-[10px] text-slate-400">
                                    {{ permission.name }}
                                </p>
                            </label>
                            <div
                                class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                <button
                                    v-if="can.updatePermission"
                                    class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/[0.06]"
                                    @click="openEditPermission(permission)"
                                >
                                    <Pencil class="h-3 w-3" />
                                </button>
                                <button
                                    v-if="can.deletePermission"
                                    class="rounded p-0.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10"
                                    @click="deletingPermission = permission"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="Object.keys(filteredModuleMap).length === 0"
                    class="py-12 text-center text-xs text-slate-400"
                >
                    No permissions match your search.
                </div>
            </div>
        </main>
    </div>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showRoleDrawer"
                class="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm"
                @click="showRoleDrawer = false"
            />
        </Transition>
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition duration-150"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div
                v-if="showRoleDrawer"
                class="fixed inset-y-0 right-0 z-50 flex w-80 flex-col bg-white shadow-2xl dark:bg-slate-950"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.03]"
                >
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white"
                    >
                        <Shield class="h-4 w-4 text-emerald-600" />
                        {{ editingRole ? 'Edit Role' : 'Create Role' }}
                    </h3>
                    <button
                        class="rounded p-1 text-slate-400 hover:bg-slate-200 dark:hover:bg-white/[0.06]"
                        @click="showRoleDrawer = false"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="flex-1 space-y-3 overflow-y-auto px-4 py-4">
                    <label class="grid gap-1">
                        <span
                            class="text-[11px] font-semibold text-slate-500 uppercase"
                            >Role Name</span
                        >
                        <input
                            v-model="roleForm.name"
                            type="text"
                            placeholder="e.g. Student"
                            class="form-input font-mono"
                            :disabled="editingRole?.name === 'Super Admin'"
                        />
                        <InputError :message="roleForm.errors.name" />
                    </label>
                </div>
                <div
                    class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-4 py-2.5 dark:border-white/10 dark:bg-white/[0.03]"
                >
                    <button
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-100 dark:border-white/10 dark:text-slate-300"
                        @click="showRoleDrawer = false"
                    >
                        Cancel
                    </button>
                    <button
                        class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="roleForm.processing"
                        @click="submitRole"
                    >
                        <Save class="h-3 w-3" />
                        {{
                            roleForm.processing
                                ? 'Saving...'
                                : editingRole
                                  ? 'Update'
                                  : 'Create'
                        }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showPermissionDrawer"
                class="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm"
                @click="showPermissionDrawer = false"
            />
        </Transition>
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition duration-150"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div
                v-if="showPermissionDrawer"
                class="fixed inset-y-0 right-0 z-50 flex w-80 flex-col bg-white shadow-2xl dark:bg-slate-950"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.03]"
                >
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white"
                    >
                        <Key class="h-4 w-4 text-sky-600" />
                        {{
                            editingPermission
                                ? 'Edit Permission'
                                : 'Create Permission'
                        }}
                    </h3>
                    <button
                        class="rounded p-1 text-slate-400 hover:bg-slate-200 dark:hover:bg-white/[0.06]"
                        @click="showPermissionDrawer = false"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="flex-1 space-y-3 overflow-y-auto px-4 py-4">
                    <label class="grid gap-1">
                        <span
                            class="text-[11px] font-semibold text-slate-500 uppercase"
                            >Permission Key</span
                        >
                        <input
                            v-model="permissionForm.name"
                            type="text"
                            placeholder="users.view"
                            class="form-input font-mono"
                        />
                        <InputError :message="permissionForm.errors.name" />
                    </label>
                    <label class="grid gap-1">
                        <span
                            class="text-[11px] font-semibold text-slate-500 uppercase"
                            >Module</span
                        >
                        <input
                            v-model="permissionForm.module"
                            type="text"
                            placeholder="Users"
                            class="form-input"
                        />
                        <InputError :message="permissionForm.errors.module" />
                    </label>
                </div>
                <div
                    class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-4 py-2.5 dark:border-white/10 dark:bg-white/[0.03]"
                >
                    <button
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-100 dark:border-white/10 dark:text-slate-300"
                        @click="showPermissionDrawer = false"
                    >
                        Cancel
                    </button>
                    <button
                        class="flex items-center gap-1.5 rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700 disabled:opacity-50"
                        :disabled="permissionForm.processing"
                        @click="submitPermission"
                    >
                        <Save class="h-3 w-3" />
                        {{
                            permissionForm.processing
                                ? 'Saving...'
                                : editingPermission
                                  ? 'Update'
                                  : 'Create'
                        }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>

    <div
        v-if="deletingRole || deletingPermission"
        class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
        @click.self="
            deletingRole = null;
            deletingPermission = null;
        "
    >
        <div
            class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl dark:bg-slate-950"
        >
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Confirm Delete
            </h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                This action cannot be undone.
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <button
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-600 dark:border-white/10 dark:text-slate-300"
                    @click="
                        deletingRole = null;
                        deletingPermission = null;
                    "
                >
                    Cancel
                </button>
                <button
                    class="flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700"
                    @click="deletingRole ? deleteRole() : deletePermission()"
                >
                    <Trash2 class="h-3 w-3" />
                    Delete
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.form-input {
    @apply h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60;
    color-scheme: light;
    background-color: #ffffff;
    color: #0f172a;
}
</style>

<style>
.dark .form-input {
    color-scheme: dark;
    border-color: rgb(255 255 255 / 0.1);
    background-color: #0f172a;
    color: #f1f5f9;
}
</style>
