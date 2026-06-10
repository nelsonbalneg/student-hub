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
    router.get(
        societiesIndex().url,
        { search: search.value },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const clearSearch = () => {
    search.value = '';
    handleSearch();
};
</script>

<template>
    <Head title="Browse Societies" />

    <AppLayout hide-header hide-footer>
        <div
            class="min-h-screen w-full space-y-8 bg-slate-50/30 px-6 py-8 lg:px-10"
        >
            <!-- Header Section -->
            <div
                class="flex flex-col justify-between gap-6 border-b border-slate-200 pb-8 md:flex-row md:items-center dark:border-slate-800"
            >
                <div class="grid gap-1">
                    <h1
                        class="text-base font-black tracking-tight tracking-widest text-slate-900 uppercase dark:text-white"
                    >
                        Browse Societies
                    </h1>
                    <p
                        class="text-[10px] font-bold tracking-tighter text-slate-400 uppercase"
                    >
                        Community Discovery & Collaboration Portal
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <div class="relative flex-1 md:w-80">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search societies..."
                            class="w-full rounded-xl border border-slate-200 bg-white py-3 pr-10 pl-10 text-[11px] font-bold tracking-tight uppercase shadow-sm transition-all outline-none placeholder:text-slate-400 focus:border-transparent focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            @keyup.enter="handleSearch"
                        />
                        <Search
                            class="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <button
                            v-if="search"
                            @click="clearSearch"
                            class="absolute top-1/2 right-4 -translate-y-1/2 rounded-full p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </div>
                    <button
                        @click="handleSearch"
                        class="rounded-xl bg-slate-900 px-6 py-3 text-[10px] font-black tracking-[0.2em] text-white uppercase shadow-lg shadow-slate-900/10 transition-all hover:bg-indigo-600"
                    >
                        Filter
                    </button>
                </div>
            </div>

            <!-- Society Cards -->
            <div
                v-if="societies.data.length > 0"
                class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
            >
                <div
                    v-for="society in societies.data"
                    :key="society.id"
                    class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:scale-[1.02] hover:shadow-xl dark:border-slate-700 dark:bg-slate-800"
                >
                    <div
                        class="relative flex h-32 items-center justify-center border-b border-slate-50 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50"
                    >
                        <img
                            v-if="society.logo_path"
                            :src="society.logo_path"
                            class="h-20 w-20 rounded-2xl object-cover shadow-2xl"
                        />
                        <div
                            v-else
                            class="flex h-20 w-20 items-center justify-center rounded-2xl bg-white text-2xl font-black text-indigo-600 shadow-xl dark:bg-slate-700 dark:text-indigo-400"
                        >
                            {{ society.acronym || society.name.charAt(0) }}
                        </div>
                        <div class="absolute top-3 right-3">
                            <span
                                v-if="
                                    society.accreditation_requests?.length > 0
                                "
                                class="rounded-lg border border-emerald-100 bg-emerald-50 px-2 py-0.5 text-[8px] font-black tracking-widest text-emerald-600 uppercase dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400"
                            >
                                Verified
                            </span>
                        </div>
                    </div>

                    <div class="space-y-4 p-5">
                        <div>
                            <div class="mb-2 flex items-center gap-2">
                                <span
                                    class="rounded border border-slate-200 bg-slate-100 px-2 py-0.5 text-[8px] font-black tracking-widest text-slate-500 uppercase dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    {{ society.society_type || 'General' }}
                                </span>
                            </div>
                            <h3
                                class="line-clamp-1 text-sm font-black tracking-tight text-slate-900 uppercase transition-colors group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                            >
                                {{ society.name }}
                            </h3>
                            <p
                                class="font-mono text-[10px] font-bold tracking-widest text-indigo-500 uppercase"
                            >
                                {{ society.acronym }}
                            </p>
                        </div>

                        <p
                            class="line-clamp-2 min-h-[32px] text-[11px] leading-relaxed font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{
                                society.description ||
                                'No formal description provided.'
                            }}
                        </p>

                        <div
                            class="flex items-center justify-between border-t border-slate-50 pt-4 dark:border-slate-700"
                        >
                            <span
                                class="text-[9px] font-black tracking-tighter text-slate-400 uppercase"
                                >{{
                                    society.college || 'University-Wide'
                                }}</span
                            >
                            <Link
                                :href="
                                    societies.show({ society: society.id }).url
                                "
                                class="flex items-center gap-1 text-[10px] font-black tracking-widest text-slate-900 uppercase transition-colors hover:text-indigo-600 dark:text-indigo-400"
                            >
                                Profile <ArrowRight class="h-3 w-3" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex flex-col items-center justify-center rounded-2xl border border-slate-200 bg-white py-20 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div
                    class="mb-4 rounded-full bg-slate-50 p-6 dark:bg-slate-900/50"
                >
                    <Search class="h-8 w-8 text-slate-400" />
                </div>
                <h3
                    class="text-base font-black tracking-widest text-slate-900 uppercase dark:text-white"
                >
                    No matching entities
                </h3>
                <p
                    class="mt-1 text-[10px] font-bold tracking-tighter text-slate-400 uppercase"
                >
                    Your query returned zero registry records.
                </p>
                <button
                    @click="clearSearch"
                    class="mt-6 rounded-xl px-6 py-2 text-[10px] font-black tracking-[0.2em] text-indigo-600 uppercase transition-all hover:bg-indigo-50"
                >
                    Reset Search
                </button>
            </div>
        </div>
    </AppLayout>
</template>
