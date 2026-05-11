<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Building2,
    FileCheck,
    Calendar,
    Plus,
    Pencil,
    Trash2,
    Save,
    X,
    ChevronRight,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import InputError from '@/components/InputError.vue';

const props = defineProps<{
    offices: any[];
    clearanceTypes: any[];
    semesters: any[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '#' },
            { title: 'Reference Lookups', href: '/admin/reference-lookups' },
        ],
    },
});

const activeTab = ref('offices');
const tabs = [
    { id: 'offices', name: 'Offices', icon: Building2 },
    { id: 'clearance-types', name: 'Clearance Types', icon: FileCheck },
    { id: 'semesters', name: 'Semesters', icon: Calendar },
];

const showModal = ref(false);
const editingItem = ref<any>(null);

// Forms
const officeForm = useForm({
    name: '',
    code: '',
    description: '',
});

const typeForm = useForm({
    name: '',
    description: '',
});

const semesterForm = useForm({
    academic_year: '',
    term: '',
    campus_id: 1 as number | null,
    is_active: false,
    start_date: '',
    end_date: '',
});

const openCreate = () => {
    editingItem.value = null;
    if (activeTab.value === 'offices') officeForm.reset();
    if (activeTab.value === 'clearance-types') typeForm.reset();
    if (activeTab.value === 'semesters') semesterForm.reset();
    showModal.value = true;
};

const openEdit = (item: any) => {
    editingItem.value = item;
    if (activeTab.value === 'offices') {
        officeForm.name = item.name;
        officeForm.code = item.code;
        officeForm.description = item.description;
    } else if (activeTab.value === 'clearance-types') {
        typeForm.name = item.name;
        typeForm.description = item.description;
    } else if (activeTab.value === 'semesters') {
        semesterForm.academic_year = item.academic_year;
        semesterForm.term = item.term;
        semesterForm.campus_id = item.campus_id;
        semesterForm.is_active = item.is_active;
        semesterForm.start_date = item.start_date ? item.start_date.split('T')[0] : '';
        semesterForm.end_date = item.end_date ? item.end_date.split('T')[0] : '';
    }
    showModal.value = true;
};

const submit = () => {
    const urlBase = '/admin/reference-lookups';
    if (activeTab.value === 'offices') {
        if (editingItem.value) {
            officeForm.patch(`${urlBase}/offices/${editingItem.value.id}`, { onSuccess: () => (showModal.value = false) });
        } else {
            officeForm.post(`${urlBase}/offices`, { onSuccess: () => (showModal.value = false) });
        }
    } else if (activeTab.value === 'clearance-types') {
        if (editingItem.value) {
            typeForm.patch(`${urlBase}/types/${editingItem.value.id}`, { onSuccess: () => (showModal.value = false) });
        } else {
            typeForm.post(`${urlBase}/types`, { onSuccess: () => (showModal.value = false) });
        }
    } else if (activeTab.value === 'semesters') {
        if (editingItem.value) {
            semesterForm.patch(`${urlBase}/semesters/${editingItem.value.id}`, { onSuccess: () => (showModal.value = false) });
        } else {
            semesterForm.post(`${urlBase}/semesters`, { onSuccess: () => (showModal.value = false) });
        }
    }
};

const deleteItem = (item: any) => {
    if (confirm('Are you sure you want to delete this item?')) {
        const urlBase = '/admin/reference-lookups';
        let url = '';
        if (activeTab.value === 'offices') url = `${urlBase}/offices/${item.id}`;
        else if (activeTab.value === 'clearance-types') url = `${urlBase}/types/${item.id}`;
        else if (activeTab.value === 'semesters') url = `${urlBase}/semesters/${item.id}`;
        
        router.delete(url);
    }
};
</script>

<template>
    <Head title="Reference Lookups" />

    <div class="flex h-[calc(100vh-3rem)] overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950">
        <!-- Sidebar -->
        <aside class="flex w-64 flex-col border-r border-slate-100 dark:border-white/10">
            <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5 dark:border-white/10">
                <span class="flex items-center gap-1.5 text-xs font-bold tracking-wide text-slate-700 uppercase dark:text-slate-200">
                    Lookups
                </span>
            </div>
            <div class="flex-1 overflow-y-auto py-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-xs transition-colors"
                    :class="activeTab === tab.id ? 'bg-emerald-50 font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-white/[0.04]'"
                    @click="activeTab = tab.id"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    {{ tab.name }}
                    <ChevronRight v-if="activeTab === tab.id" class="ml-auto h-3 w-3" />
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 bg-white px-4 py-2.5 dark:border-white/10 dark:bg-slate-950">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ tabs.find(t => t.id === activeTab)?.name }}</h2>
                </div>
                <button
                    class="flex h-7 items-center gap-1.5 rounded-lg bg-emerald-600 px-2.5 text-[11px] font-semibold text-white hover:bg-emerald-700"
                    @click="openCreate"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Add New
                </button>
            </div>

            <div class="flex-1 overflow-y-auto">
                <!-- Offices Table -->
                <table v-if="activeTab === 'offices'" class="min-w-full divide-y divide-slate-100 text-[11px]">
                    <thead class="bg-slate-50/50 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left font-bold text-slate-400 uppercase">Code</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-400 uppercase">Name</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-400 uppercase">Description</th>
                            <th class="px-4 py-3 text-right font-bold text-slate-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="office in offices" :key="office.id" class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-mono text-emerald-600 font-bold">{{ office.code || '-' }}</td>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ office.name }}</td>
                            <td class="px-4 py-3 text-slate-400 italic truncate max-w-xs">{{ office.description || 'No description' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-1">
                                    <button @click="openEdit(office)" class="p-1 text-slate-400 hover:text-emerald-600"><Pencil class="h-3.5 w-3.5" /></button>
                                    <button @click="deleteItem(office)" class="p-1 text-slate-400 hover:text-red-500"><Trash2 class="h-3.5 w-3.5" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Clearance Types Table -->
                <table v-if="activeTab === 'clearance-types'" class="min-w-full divide-y divide-slate-100 text-[11px]">
                    <thead class="bg-slate-50/50 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left font-bold text-slate-400 uppercase">Name</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-400 uppercase">Description</th>
                            <th class="px-4 py-3 text-right font-bold text-slate-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="type in clearanceTypes" :key="type.id" class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-bold text-slate-700">{{ type.name }}</td>
                            <td class="px-4 py-3 text-slate-400 italic">{{ type.description || 'No description' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-1">
                                    <button @click="openEdit(type)" class="p-1 text-slate-400 hover:text-emerald-600"><Pencil class="h-3.5 w-3.5" /></button>
                                    <button @click="deleteItem(type)" class="p-1 text-slate-400 hover:text-red-500"><Trash2 class="h-3.5 w-3.5" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Semesters Table -->
                <table v-if="activeTab === 'semesters'" class="min-w-full divide-y divide-slate-100 text-[11px]">
                    <thead class="bg-slate-50/50 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left font-bold text-slate-400 uppercase">Academic Year</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-400 uppercase">Term</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-400 uppercase">Status</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-400 uppercase">Campus</th>
                            <th class="px-4 py-3 text-right font-bold text-slate-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="sem in semesters" :key="sem.id" class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-bold text-slate-700">{{ sem.academic_year }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ sem.term }}</td>
                            <td class="px-4 py-3">
                                <span v-if="sem.is_active" class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-bold uppercase text-[9px]">Active</span>
                                <span v-else class="bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded font-bold uppercase text-[9px]">Inactive</span>
                            </td>
                            <td class="px-4 py-3 text-[10px] text-slate-400">{{ sem.campus_name || '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-1">
                                    <button @click="openEdit(sem)" class="p-1 text-slate-400 hover:text-emerald-600"><Pencil class="h-3.5 w-3.5" /></button>
                                    <button @click="deleteItem(sem)" class="p-1 text-slate-400 hover:text-red-500"><Trash2 class="h-3.5 w-3.5" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div v-if="showModal" class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4" @click.self="showModal = false">
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl dark:bg-slate-950">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">
                {{ editingItem ? 'Edit' : 'Add' }} {{ tabs.find(t => t.id === activeTab)?.name }}
            </h3>
            
            <form @submit.prevent="submit" class="grid gap-4">
                <!-- Office Form -->
                <div v-if="activeTab === 'offices'" class="grid gap-3">
                    <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                        Name
                        <input v-model="officeForm.name" type="text" class="h-9 rounded-lg border border-slate-200 px-3 text-xs" />
                        <InputError :message="officeForm.errors.name" />
                    </label>
                    <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                        Code
                        <input v-model="officeForm.code" type="text" class="h-9 rounded-lg border border-slate-200 px-3 text-xs" />
                        <InputError :message="officeForm.errors.code" />
                    </label>
                    <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                        Description
                        <textarea v-model="officeForm.description" class="rounded-lg border border-slate-200 p-2 text-xs" rows="2"></textarea>
                    </label>
                </div>

                <!-- Type Form -->
                <div v-if="activeTab === 'clearance-types'" class="grid gap-3">
                    <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                        Name
                        <input v-model="typeForm.name" type="text" class="h-9 rounded-lg border border-slate-200 px-3 text-xs" />
                        <InputError :message="typeForm.errors.name" />
                    </label>
                    <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                        Description
                        <textarea v-model="typeForm.description" class="rounded-lg border border-slate-200 p-2 text-xs" rows="2"></textarea>
                    </label>
                </div>

                <!-- Semester Form -->
                <div v-if="activeTab === 'semesters'" class="grid gap-3">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                            Academic Year
                            <input v-model="semesterForm.academic_year" type="text" placeholder="2023-2024" class="h-9 rounded-lg border border-slate-200 px-3 text-xs" />
                        </label>
                        <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                            Term
                            <input v-model="semesterForm.term" type="text" placeholder="1st Semester" class="h-9 rounded-lg border border-slate-200 px-3 text-xs" />
                        </label>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <input v-model="semesterForm.is_active" type="checkbox" id="is_active" class="h-3.5 w-3.5 accent-emerald-600" />
                            <label for="is_active" class="text-[11px] font-bold text-slate-500 uppercase">Mark as Active</label>
                        </div>
                        <div class="flex-1">
                            <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                                Campus
                                <select v-model="semesterForm.campus_id" class="h-9 rounded-lg border border-slate-200 px-3 text-xs bg-white">
                                    <option :value="1">Main Campus</option>
                                    <option :value="3">USM KCC</option>
                                    <option :value="4">Advance Education</option>
                                </select>
                            </label>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                            Start Date
                            <input v-model="semesterForm.start_date" type="date" class="h-9 rounded-lg border border-slate-200 px-3 text-xs" />
                        </label>
                        <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                            End Date
                            <input v-model="semesterForm.end_date" type="date" class="h-9 rounded-lg border border-slate-200 px-3 text-xs" />
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2 border-t pt-4">
                    <button type="button" @click="showModal = false" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700">Cancel</button>
                    <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1.5 hover:bg-emerald-700">
                        <Save class="h-3.5 w-3.5" />
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
