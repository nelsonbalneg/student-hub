<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { 
    Users, 
    FileText, 
    ShieldCheck, 
    CheckCircle2,
    Building2,
    ArrowRight
} from 'lucide-vue-next';

const steps = [
    { id: 1, title: 'Basic Info', description: 'Society name & acronym', status: 'current' },
    { id: 2, title: 'Officers', description: 'Assign leadership roles', status: 'upcoming' },
    { id: 3, title: 'Constitution', description: 'Upload bylaws & rules', status: 'upcoming' },
    { id: 4, title: 'Review', description: 'Final submission', status: 'upcoming' },
];
</script>

<template>
    <Head title="Society Registration" />

  
        <div class="w-full max-w-none mx-0 px-6 lg:px-8 py-8 space-y-8 bg-slate-50/30 min-h-screen">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-8">
                <div class="grid gap-1">
                    <h1 class="text-base font-black tracking-tight text-slate-900 dark:text-white uppercase">Society Registration</h1>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Initialize a new student organization</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="flex flex-col items-end">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Process Status</span>
                        <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-tight">Drafting Application</span>
                    </div>
                    <div class="h-10 w-10 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                        <Building2 class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="grid grid-cols-1 xl:grid-cols-4 gap-8">
                <!-- Left Column: Steps -->
                <div class="xl:col-span-1 space-y-4">
                    <div v-for="step in steps" :key="step.id" 
                        class="p-4 rounded-2xl border transition-all"
                        :class="step.status === 'current' 
                            ? 'bg-white dark:bg-slate-800 border-blue-200 dark:border-blue-800 shadow-sm' 
                            : 'bg-transparent border-slate-100 dark:border-slate-800 opacity-60'">
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg flex items-center justify-center text-[11px] font-black"
                                :class="step.status === 'current' 
                                    ? 'bg-blue-600 text-white' 
                                    : 'bg-slate-100 dark:bg-slate-800 text-slate-400'">
                                {{ step.id }}
                            </div>
                            <div>
                                <h3 class="text-[11px] font-black uppercase tracking-tight"
                                    :class="step.status === 'current' ? 'text-slate-900 dark:text-white' : 'text-slate-400'">
                                    {{ step.title }}
                                </h3>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">{{ step.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Form Container -->
                <div class="xl:col-span-3">
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                        <div class="p-8 border-b border-slate-100 dark:border-slate-700/50">
                            <h2 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-tight">Basic Information</h2>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Identify your organization in the registry</p>
                        </div>
                        
                        <div class="p-8 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-2">
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Official Name</label>
                                    <input type="text" placeholder="e.g. Society of Computer Scientists" 
                                        class="w-full bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-[13px] font-semibold focus:ring-2 focus:ring-blue-500/20 transition-all" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Acronym</label>
                                    <input type="text" placeholder="e.g. SOCS" 
                                        class="w-full bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-[13px] font-semibold focus:ring-2 focus:ring-blue-500/20 transition-all" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Organization Description</label>
                                <textarea rows="4" placeholder="Briefly describe the purpose and goals of your society..."
                                    class="w-full bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-[13px] font-semibold focus:ring-2 focus:ring-blue-500/20 transition-all resize-none"></textarea>
                            </div>
                        </div>

                        <div class="p-8 bg-slate-50/50 dark:bg-slate-900/20 border-t border-slate-100 dark:border-slate-700/50 flex justify-end">
                            <button class="inline-flex items-center gap-2 px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-xl transition-all shadow-lg shadow-blue-200 dark:shadow-none">
                                Next Step
                                <ArrowRight class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
   
</template>
