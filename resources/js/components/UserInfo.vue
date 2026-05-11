<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);
</script>

<template>
    <Avatar
        class="h-8 w-8 overflow-hidden rounded-full ring-1 ring-slate-200 dark:ring-slate-700"
    >
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback
            class="rounded-full bg-slate-100 text-xs font-bold text-slate-800 dark:bg-slate-800 dark:text-white"
        >
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-semibold text-slate-950 dark:text-white">{{
            user.name
        }}</span>
        <span
            v-if="showEmail"
            class="truncate text-xs font-medium text-slate-400 dark:text-slate-500"
            >{{ user.email }}</span
        >
    </div>
</template>
