<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Search, X, ArrowRight } from 'lucide-vue-next';
import societies, { index as societiesIndex } from '@/routes/societies';
import { dashboard } from '@/routes';
import AppLayout from '@/layouts/AppLayout.vue';

interface Props {
    societies: {
        data: any[];
        links: any[];
    };
    filters: {
        search?: string;
    };
}

const props = defineProps<Props>();
const search = ref(props.filters.search || '');

const handleSearch = () => {
    router.get(societiesIndex().url, { search: search.value }, {
        preserveState: true,
        replace: true
    });
};

const clearSearch = () => {
    search.value = '';
    handleSearch();
};
</script>

<template>
    <Head title="Browse Societies" />

    <AppLayout hide-header hide-footer>
        <div class="w-full px-6 lg:px-10 py-8 space-y-8 bg-slate-50/30 min-h-screen">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 border-b border-slate-200 dark:border-slate-800 pb-8">
                <div class="grid gap-1">
                    <h1 class="text-base font-black tracking-tight text-slate-900 dark:text-white uppercase tracking-widest">Browse Societies</h1>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Community Discovery & Collaboration Portal</p>
                </div>

                <div class="flex items-center gap-2">
                    <div class="relative flex-1 md:w-80">
                        <input 
                            v-model="search"
                            type="text" 
                            placeholder="Search societies..." 
                            class="w-full pl-10 pr-10 py-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition-all dark:text-white shadow-sm text-[11px] font-bold uppercase tracking-tight placeholder:text-slate-400"
                            @keyup.enter="handleSearch"
                        >
                        <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                        <button 
                            v-if="search"
                            @click="clearSearch"
                            class="absolute right-4 top-1/2 -translate-y-1/2 p-1 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-full text-slate-400"
                        >
                            <X class="w-3 h-3" />
                        </button>
                    </div>
                    <button 
                        @click="handleSearch"
                        class="px-6 py-3 bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-xl hover:bg-indigo-600 transition-all shadow-lg shadow-slate-900/10"
                    >
                        Filter
                    </button>
                </div>
            </div>

            <!-- Society Cards -->
            <div v-if="societies.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-6">
                <div v-for="society in societies.data" :key="society.id" class="group bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm hover:shadow-xl transition-all hover:scale-[1.02]">
                    <div class="h-32 bg-slate-50 dark:bg-slate-900/50 relative flex items-center justify-center border-b border-slate-50 dark:border-slate-700">
                        <img v-if="society.logo_path" :src="society.logo_path" class="w-20 h-20 rounded-2xl object-cover shadow-2xl">
                        <div v-else class="w-20 h-20 rounded-2xl bg-white dark:bg-slate-700 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-black text-2xl shadow-xl">
                            {{ society.acronym || society.name.charAt(0) }}
                        </div>
                        <div class="absolute top-3 right-3">
                             <span v-if="society.accreditation_requests?.length > 0" class="px-2 py-0.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[8px] font-black rounded-lg border border-emerald-100 dark:border-emerald-500/20 uppercase tracking-widest">
                                Verified
                            </span>
                        </div>
                    </div>

                    <div class="p-5 space-y-4">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-0.5 bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 text-[8px] font-black rounded uppercase tracking-widest border border-slate-200 dark:border-slate-600">
                                    {{ society.society_type || 'General' }}
                                </span>
                            </div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white line-clamp-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors uppercase tracking-tight">
                                {{ society.name }}
                            </h3>
                            <p class="text-[10px] font-mono text-indigo-500 font-bold uppercase tracking-widest">{{ society.acronym }}</p>
                        </div>

                        <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400 line-clamp-2 min-h-[32px] leading-relaxed">
                            {{ society.description || 'No formal description provided.' }}
                        </p>

                        <div class="pt-4 border-t border-slate-50 dark:border-slate-700 flex items-center justify-between">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-tighter">{{ society.college || 'University-Wide' }}</span>
                            <Link :href="societies.show({ society: society.id }).url" class="text-[10px] font-black text-slate-900 dark:text-indigo-400 hover:text-indigo-600 transition-colors flex items-center gap-1 uppercase tracking-widest">
                                Profile <ArrowRight class="w-3 h-3" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-20 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="p-6 bg-slate-50 dark:bg-slate-900/50 rounded-full mb-4">
                    <Search class="w-8 h-8 text-slate-400" />
                </div>
                <h3 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-widest">No matching entities</h3>
                <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-tighter">Your query returned zero registry records.</p>
                <button @click="clearSearch" class="mt-6 px-6 py-2 text-[10px] font-black text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all uppercase tracking-[0.2em]">Reset Search</button>
            </div>
        </div>
    </AppLayout>
</template>
