<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    Plus, 
    Search, 
    Building2, 
    MapPin, 
    ChevronRight,
    MoreHorizontal,
    Trash2,
    Edit,
    CheckCircle2,
    XCircle
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import * as campusRoutes from '@/routes/site-settings/campuses';

defineOptions({
    layout: SiteSettingsLayout,
});

interface Campus {
    id: number;
    campus_name: string;
    campus_address: string;
    campus_logo_path: string;
    real_campus_id: string;
    status: 'Active' | 'Inactive';
    academic_terms_count: number;
}

const props = defineProps<{
    campuses: {
        data: Campus[];
        links: any[];
    };
    filters: {
        search: string;
    };
}>();

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedCampus = ref<Campus | null>(null);
const search = ref(props.filters.search || '');

const form = useForm({
    campus_name: '',
    campus_address: '',
    real_campus_id: '',
    status: 'Active' as 'Active' | 'Inactive',
    logo: null as File | null,
});

const createCampus = () => {
    form.post(campusRoutes.store.url(), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const editCampus = (campus: Campus) => {
    selectedCampus.value = campus;
    form.campus_name = campus.campus_name;
    form.campus_address = campus.campus_address;
    form.real_campus_id = campus.real_campus_id;
    form.status = campus.status;
    showEditModal.value = true;
};

const updateCampus = () => {
    if (!selectedCampus.value) return;
    
    // Use POST with _method PATCH for file upload support
    form.transform((data) => ({
        ...data,
        _method: 'PATCH',
    })).post(campusRoutes.update.url(selectedCampus.value.id), {
        onSuccess: () => {
            showEditModal.value = false;
            form.reset();
        },
    });
};

const confirmDelete = (campus: Campus) => {
    selectedCampus.value = campus;
    showDeleteModal.value = true;
};

const deleteCampus = () => {
    if (!selectedCampus.value) return;
    form.delete(campusRoutes.destroy.url(selectedCampus.value.id), {
        onSuccess: () => (showDeleteModal.value = false),
    });
};

const handleSearch = () => {
    // Implement search logic here or use a watcher
};
</script>

<template>
    <Head title="Site Settings - Campuses" />

    <div class="p-6 lg:p-10 space-y-10">
        <!-- Header Section -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Campuses</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Manage your campus identities and academic configurations.</p>
            </div>
                <Button @click="showCreateModal = true" class="bg-sky-600 hover:bg-sky-700 text-white shadow-lg shadow-sky-500/20">
                    <Plus class="mr-2 size-4" />
                    New Campus
                </Button>
            </div>

            <!-- Stats/Cards Section -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="campus in campuses.data" :key="campus.id" class="group relative overflow-hidden transition-all hover:shadow-md dark:bg-slate-900/50">
                    <CardContent class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="relative flex size-12 items-center justify-center rounded-xl bg-slate-100 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-white/10">
                                    <img v-if="campus.campus_logo_path" :src="`/storage/${campus.campus_logo_path}`" class="size-8 object-contain" alt="Logo" />
                                    <Building2 v-else class="size-6 text-slate-400" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="truncate font-bold text-slate-900 dark:text-white">{{ campus.campus_name }}</h3>
                                    <p class="flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                                        <MapPin class="size-3" />
                                        {{ campus.campus_address || 'No address set' }}
                                    </p>
                                </div>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-8 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-40">
                                    <DropdownMenuItem @click="editCampus(campus)">
                                        <Edit class="mr-2 size-4" /> Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="text-red-600" @click="confirmDelete(campus)">
                                        <Trash2 class="mr-2 size-4" /> Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4 dark:border-white/5">
                            <div class="flex flex-col gap-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Real ID</span>
                                <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">#{{ campus.real_campus_id || 'N/A' }}</span>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <Badge :variant="campus.status === 'Active' ? 'default' : 'secondary'" 
                                    :class="campus.status === 'Active' ? 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400' : ''">
                                    <component :is="campus.status === 'Active' ? CheckCircle2 : XCircle" class="mr-1 size-3" />
                                    {{ campus.status }}
                                </Badge>
                            </div>
                        </div>

                        <Link :href="campusRoutes.show.url(campus.id)" class="mt-4 flex w-full items-center justify-center rounded-lg bg-slate-50 py-2 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:bg-white/5 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
                            View Details
                            <ChevronRight class="ml-1 size-3.5" />
                        </Link>
                    </CardContent>
            </Card>
        </div>

        <!-- Empty State -->
        <div v-if="campuses.data.length === 0" class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 py-20 dark:border-white/5">
            <div class="rounded-full bg-slate-50 p-4 dark:bg-white/5">
                <Building2 class="size-10 text-slate-300" />
            </div>
            <h3 class="mt-4 text-lg font-bold text-slate-950 dark:text-white">No campuses found</h3>
            <p class="mt-1 text-sm text-slate-500">Get started by creating your first campus identity.</p>
            <Button @click="showCreateModal = true" variant="outline" class="mt-6">
                <Plus class="mr-2 size-4" />
                New Campus
            </Button>
        </div>

        <!-- Create/Edit Modals -->
        <Dialog v-model:open="showCreateModal">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>New Campus</DialogTitle>
                    <DialogDescription>Add a new campus identity to the system.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="createCampus" class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="name">Campus Name</Label>
                        <Input id="name" v-model="form.campus_name" placeholder="Main Campus" required />
                        <div v-if="form.errors.campus_name" class="text-xs text-red-500">{{ form.errors.campus_name }}</div>
                    </div>
                    <div class="space-y-2">
                        <Label for="address">Address</Label>
                        <Input id="address" v-model="form.campus_address" placeholder="123 Academic St." />
                        <div v-if="form.errors.campus_address" class="text-xs text-red-500">{{ form.errors.campus_address }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="real_id">Real Campus ID</Label>
                            <Input id="real_id" v-model="form.real_campus_id" placeholder="1" />
                            <div v-if="form.errors.real_campus_id" class="text-xs text-red-500">{{ form.errors.real_campus_id }}</div>
                        </div>
                        <div class="space-y-2">
                            <Label for="status">Status</Label>
                            <Select v-model="form.status">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Active">Active</SelectItem>
                                    <SelectItem value="Inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-if="form.errors.status" class="text-xs text-red-500">{{ form.errors.status }}</div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="logo">Campus Logo</Label>
                        <Input id="logo" type="file" @input="form.logo = $event.target.files[0]" accept="image/*" />
                        <div v-if="form.errors.logo" class="text-xs text-red-500 font-medium">{{ form.errors.logo }}</div>
                    </div>
                    <DialogFooter class="pt-4">
                        <Button type="submit" :disabled="form.processing" class="bg-sky-600 hover:bg-sky-700">Save Campus</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showEditModal">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Edit Campus</DialogTitle>
                    <DialogDescription>Update campus details and configuration.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="updateCampus" class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="edit_name">Campus Name</Label>
                        <Input id="edit_name" v-model="form.campus_name" required />
                        <div v-if="form.errors.campus_name" class="text-xs text-red-500">{{ form.errors.campus_name }}</div>
                    </div>
                    <div class="space-y-2">
                        <Label for="edit_address">Address</Label>
                        <Input id="edit_address" v-model="form.campus_address" />
                        <div v-if="form.errors.campus_address" class="text-xs text-red-500">{{ form.errors.campus_address }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="edit_real_id">Real Campus ID</Label>
                            <Input id="edit_real_id" v-model="form.real_campus_id" />
                            <div v-if="form.errors.real_campus_id" class="text-xs text-red-500">{{ form.errors.real_campus_id }}</div>
                        </div>
                        <div class="space-y-2">
                            <Label for="edit_status">Status</Label>
                            <Select v-model="form.status">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Active">Active</SelectItem>
                                    <SelectItem value="Inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-if="form.errors.status" class="text-xs text-red-500">{{ form.errors.status }}</div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="edit_logo">Campus Logo (Leave blank to keep current)</Label>
                        <Input id="edit_logo" type="file" @input="form.logo = $event.target.files[0]" accept="image/*" />
                        <div v-if="form.errors.logo" class="text-xs text-red-500 font-medium">{{ form.errors.logo }}</div>
                    </div>
                    <DialogFooter class="pt-4">
                        <Button type="submit" :disabled="form.processing" class="bg-sky-600 hover:bg-sky-700">Update Campus</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmationModal
            v-if="selectedCampus"
            :show="showDeleteModal"
            title="Delete Campus"
            :message="`Are you sure you want to delete ${selectedCampus.campus_name}? This action cannot be undone.`"
            confirm-button-text="Delete Campus"
            @confirm="deleteCampus"
            @close="showDeleteModal = false"
        />
    </div>
</template>
