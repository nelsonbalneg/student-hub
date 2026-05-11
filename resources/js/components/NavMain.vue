<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

withDefaults(
    defineProps<{
        items: NavItem[];
        label?: string;
    }>(),
    {
        label: undefined,
    },
);

const page = usePage();

const currentUrl = computed(() => {
    const url = new URL(
        page.url,
        typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost',
    );

    return {
        path: url.pathname,
        searchParams: url.searchParams,
    };
});

const isCurrentNavigationItem = (href: NavItem['href']): boolean => {
    const urlString = toUrl(href);

    if (!urlString || urlString === '#') {
        return false;
    }

    const targetUrl = new URL(
        urlString,
        typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost',
    );

    if (targetUrl.pathname !== currentUrl.value.path) {
        return false;
    }

    if (!targetUrl.search) {
        return true;
    }

    return Array.from(targetUrl.searchParams.entries()).every(
        ([key, value]) => currentUrl.value.searchParams.get(key) === value,
    );
};

const hasCurrentChild = (item: NavItem): boolean =>
    item.items?.some((subItem) => isCurrentNavigationItem(subItem.href)) ??
    false;
</script>

<template>
    <SidebarGroup class="px-2 py-2">
        <SidebarGroupLabel
            v-if="label"
            class="mt-3 mb-1.5 px-2 text-[10px] font-bold tracking-[0.18em] text-slate-400 uppercase select-none group-data-[collapsible=icon]:hidden first:mt-1 dark:text-slate-600"
        >
            {{ label }}
        </SidebarGroupLabel>

        <SidebarMenu class="gap-1">
            <template v-for="item in items" :key="item.title">
                <Collapsible
                    v-if="item.items && item.items.length > 0"
                    as-child
                    :default-open="
                        item.isActive ||
                        isCurrentNavigationItem(item.href) ||
                        hasCurrentChild(item)
                    "
                    class="group/collapsible"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :tooltip="item.title"
                                :is-active="
                                    isCurrentNavigationItem(item.href) ||
                                    hasCurrentChild(item)
                                "
                                class="h-9 rounded-lg px-2.5 text-[13px] font-semibold tracking-tight text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-950 data-[active=true]:bg-sky-50 data-[active=true]:text-slate-950 dark:text-slate-400 dark:hover:bg-white/[0.05] dark:hover:text-slate-100 dark:data-[active=true]:bg-sky-500/10 dark:data-[active=true]:text-sky-200"
                            >
                                <component :is="item.icon" v-if="item.icon" />
                                <span>{{ item.title }}</span>
                                <span
                                    v-if="item.badge"
                                    class="ml-auto text-sm font-medium text-slate-400 dark:text-slate-500"
                                >
                                    {{ item.badge }}
                                </span>
                                <ChevronDown
                                    class="size-3.5 text-slate-400 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-180 dark:text-slate-600"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub
                                class="mr-0 ml-4 gap-1 border-slate-200 px-2.5 py-1 dark:border-white/5"
                            >
                                <SidebarMenuSubItem
                                    v-for="subItem in item.items"
                                    :key="subItem.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="
                                            isCurrentNavigationItem(
                                                subItem.href,
                                            )
                                        "
                                        class="h-8 rounded-md px-2 text-[12px] font-semibold text-slate-500 transition-all hover:bg-slate-100 hover:text-slate-950 data-[active=true]:bg-sky-50 data-[active=true]:text-sky-700 dark:text-slate-500 dark:hover:bg-white/[0.05] dark:hover:text-slate-300 dark:data-[active=true]:bg-sky-500/10 dark:data-[active=true]:text-sky-300"
                                    >
                                        <Link :href="subItem.href">
                                            <component
                                                :is="subItem.icon"
                                                v-if="subItem.icon"
                                            />
                                            <span>{{ subItem.title }}</span>
                                            <span
                                                v-if="subItem.badge"
                                                class="ml-auto text-sm font-medium text-slate-400 dark:text-slate-500"
                                            >
                                                {{ subItem.badge }}
                                            </span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>

                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentNavigationItem(item.href)"
                        :tooltip="item.title"
                        class="h-9 rounded-lg px-2.5 text-[13px] font-semibold tracking-tight text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-950 data-[active=true]:bg-sky-50 data-[active=true]:text-slate-950 dark:text-slate-400 dark:hover:bg-white/[0.05] dark:hover:text-slate-100 dark:data-[active=true]:bg-sky-500/10 dark:data-[active=true]:text-sky-200"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" v-if="item.icon" />
                            <span>{{ item.title }}</span>
                            <span
                                v-if="isCurrentNavigationItem(item.href)"
                                class="ml-auto size-1.5 rounded-full bg-sky-500 shadow-[0_0_8px_rgba(14,165,233,0.35)] group-data-[collapsible=icon]:hidden dark:bg-sky-400"
                            />
                            <span
                                v-if="item.badge"
                                :class="[
                                    'ml-auto min-w-5 rounded-md px-1.5 text-center text-[11px] font-bold group-data-[collapsible=icon]:hidden',
                                    item.badge === '!'
                                        ? 'bg-slate-100 text-slate-400 dark:bg-white/[0.06] dark:text-slate-300'
                                        : 'bg-red-500 text-white',
                                ]"
                            >
                                {{ item.badge }}
                            </span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
