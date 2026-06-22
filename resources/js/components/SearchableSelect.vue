<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { ChevronDown, Search, X } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: string;
    options: string[];
    placeholder?: string;
    emptyText?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const open = ref(false);
const search = ref('');
const containerRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);

const toggleDropdown = async () => {
    open.value = !open.value;
    if (open.value) {
        search.value = '';
        await nextTick();
        searchInputRef.value?.focus();
    }
};

const closeDropdown = () => {
    open.value = false;
};

const selectOption = (val: string) => {
    emit('update:modelValue', val);
    closeDropdown();
};

const clearSelection = () => {
    emit('update:modelValue', '');
    closeDropdown();
};

const filteredOptions = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) return props.options;
    return props.options.filter(opt => 
        (opt || '').toLowerCase().includes(q)
    );
});

const handleClickOutside = (event: MouseEvent) => {
    if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
        closeDropdown();
    }
};

onMounted(() => {
    window.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    window.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <!-- Trigger Button -->
        <button
            type="button"
            @click="toggleDropdown"
            class="flex h-10 w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-3 text-[13px] text-slate-800 transition-all outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-800 dark:text-white"
        >
            <span class="truncate">
                {{ modelValue || emptyText || 'All' }}
            </span>
            <ChevronDown class="size-4 shrink-0 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': open }" />
        </button>

        <!-- Dropdown Menu -->
        <div
            v-show="open"
            class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg outline-none dark:border-white/10 dark:bg-slate-900"
        >
            <!-- Search bar -->
            <div class="relative border-b border-slate-100 p-2 dark:border-white/5">
                <input
                    ref="searchInputRef"
                    v-model="search"
                    type="text"
                    placeholder="Search..."
                    class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-7 text-[12px] text-slate-800 transition outline-none focus:border-emerald-500 focus:bg-white dark:border-white/10 dark:bg-slate-800/50 dark:text-white dark:focus:bg-slate-800"
                />
                <Search class="absolute top-1/2 left-4 size-3.5 -translate-y-1/2 text-slate-400" />
                <button
                    v-if="search"
                    type="button"
                    @click="search = ''"
                    class="absolute top-1/2 right-4 flex size-4 -translate-y-1/2 items-center justify-center rounded text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5"
                >
                    <X class="size-3" />
                </button>
            </div>

            <!-- Options list -->
            <div class="max-h-48 overflow-y-auto p-1">
                <!-- Clear / All option -->
                <button
                    type="button"
                    @click="clearSelection"
                    :class="[
                        'flex w-full items-center rounded-lg px-2.5 py-1.5 text-left text-[12px] transition',
                        !modelValue
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-slate-100'
                    ]"
                >
                    {{ emptyText || 'All' }}
                </button>

                <!-- Dynamic options -->
                <button
                    v-for="opt in filteredOptions"
                    :key="opt"
                    type="button"
                    @click="selectOption(opt)"
                    :class="[
                        'flex w-full items-center rounded-lg px-2.5 py-1.5 text-left text-[12px] transition',
                        modelValue === opt
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-slate-100'
                    ]"
                >
                    {{ opt }}
                </button>

                <div
                    v-if="filteredOptions.length === 0"
                    class="py-4 text-center text-[11px] text-slate-400"
                >
                    No matches found
                </div>
            </div>
        </div>
    </div>
</template>
