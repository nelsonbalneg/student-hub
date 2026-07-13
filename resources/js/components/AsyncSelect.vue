<script setup lang="ts">
import { ref, unref, computed, watch, nextTick } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { Loader2, ChevronDown, Check, Search } from 'lucide-vue-next';

type SelectOption = { id: string; text: string };
type Select2Payload = {
    results: SelectOption[];
    pagination: { more: boolean };
};

const props = defineProps<{
    modelValue: string;
    selected: SelectOption | null;
    endpoint: string;
    params?: Record<string, string | number | undefined>;
    placeholder: string;
    disabled?: boolean;
    minInput?: number;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'select', option: SelectOption | null): void;
}>();

const isOpen = ref(false);
const term = ref('');
const page = ref(1);
const options = ref<SelectOption[]>(props.selected ? [props.selected] : []);
const loading = ref(false);
const more = ref(false);
const containerRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);

onClickOutside(containerRef, () => {
    isOpen.value = false;
});

const selectedText = computed(() => {
    if (props.selected) return props.selected.text;
    const found = options.value.find(opt => opt.id === props.modelValue);
    return found ? found.text : props.placeholder;
});

const fetchOptions = async (reset = true) => {
    if (props.disabled) return;
    if (term.value.length < (props.minInput ?? 0)) {
        options.value = props.selected ? [props.selected] : [];
        more.value = false;
        return;
    }

    loading.value = true;
    const nextPage = reset ? 1 : page.value + 1;
    const queryParams = new URLSearchParams();
    queryParams.set('page', String(nextPage));

    if (term.value) {
        queryParams.set('q', term.value);
    }

    if (props.params) {
        Object.entries(props.params).forEach(([key, value]) => {
            if (value !== undefined && value !== '') {
                const unwrapped = unref(value);
                if (unwrapped !== undefined && unwrapped !== '') {
                    queryParams.set(key, String(unwrapped));
                }
            }
        });
    }

    try {
        const response = await fetch(`${props.endpoint}?${queryParams.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = (await response.json()) as Select2Payload;

        if (reset) {
            page.value = 1;
            options.value = payload.results;
        } else {
            page.value = nextPage;
            options.value = [...options.value, ...payload.results];
        }
        more.value = payload.pagination.more;
    } finally {
        loading.value = false;
    }
};

let timer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.selected,
    (selected) => {
        options.value = selected ? [selected] : [];
    },
);

watch(
    () => props.params,
    () => {
        term.value = '';
        options.value = props.selected ? [props.selected] : [];
        more.value = false;
        fetchOptions(true);
    },
    { deep: true },
);

watch(term, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        fetchOptions(true);
    }, 300);
});

const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        term.value = '';
        fetchOptions(true);
        nextTick(() => {
            searchInputRef.value?.focus();
        });
    }
};

const selectOption = (option: SelectOption | null) => {
    emit('update:modelValue', option ? option.id : '');
    emit('select', option);
    isOpen.value = false;
};
</script>

<template>
    <div class="relative w-full text-left" ref="containerRef">
        <!-- Trigger -->
        <button
            type="button"
            :disabled="disabled"
            @click="toggleDropdown"
            class="flex w-full items-center justify-between rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm ring-offset-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-slate-900 dark:ring-offset-slate-950 dark:focus:ring-emerald-500"
            :class="!modelValue ? 'text-slate-500 dark:text-slate-400' : 'text-slate-900 dark:text-slate-100'"
        >
            <span class="truncate">{{ selectedText }}</span>
            <ChevronDown class="h-4 w-4 opacity-50 shrink-0" />
        </button>

        <!-- Dropdown -->
        <div
            v-if="isOpen"
            class="absolute z-50 mt-1 max-h-72 min-w-full w-[250px] sm:w-[350px] max-w-sm overflow-hidden rounded-md border border-slate-200 bg-white text-slate-950 shadow-md dark:border-white/10 dark:bg-slate-900 dark:text-slate-50 flex flex-col"
        >
            <div class="p-2 border-b border-slate-100 dark:border-white/5 relative">
                <Search class="absolute left-4 top-4.5 h-4 w-4 text-slate-400" />
                <input
                    ref="searchInputRef"
                    v-model="term"
                    class="flex h-9 w-full rounded-md border border-transparent bg-slate-50 dark:bg-white/5 pl-9 pr-3 text-sm outline-none placeholder:text-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    placeholder="Search..."
                />
            </div>
            
            <div class="flex-1 overflow-y-auto p-1 text-sm h-full max-h-48">
                <div v-if="loading && page === 1" class="flex items-center justify-center p-4 text-slate-500">
                    <Loader2 class="h-4 w-4 animate-spin mr-2" />
                    <span>Loading...</span>
                </div>
                
                <template v-else>
                    <div
                        v-if="options.length === 0"
                        class="p-4 text-center text-slate-500 text-sm"
                    >
                        No results found.
                    </div>
                    
                    <button
                        v-else
                        v-for="option in options"
                        :key="option.id"
                        type="button"
                        @click="selectOption(option)"
                        class="relative flex w-full cursor-default select-none items-center rounded-sm py-2 pl-8 pr-2 text-sm outline-none hover:bg-slate-100 hover:text-slate-900 focus:bg-slate-100 focus:text-slate-900 dark:hover:bg-white/10 dark:hover:text-slate-50 dark:focus:bg-white/10 dark:focus:text-slate-50 text-left"
                    >
                        <span v-if="modelValue === option.id" class="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
                            <Check class="h-4 w-4" />
                        </span>
                        <span class="whitespace-normal break-words text-left">{{ option.text }}</span>
                    </button>

                    <button
                        v-if="more"
                        type="button"
                        class="w-full p-2 text-xs text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10 text-center font-medium rounded-sm mt-1"
                        @click="fetchOptions(false)"
                    >
                        <span v-if="loading" class="flex items-center justify-center"><Loader2 class="h-3 w-3 animate-spin mr-1"/> Loading...</span>
                        <span v-else>Load more</span>
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>
