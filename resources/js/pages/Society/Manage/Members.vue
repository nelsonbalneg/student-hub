<script setup lang="ts">
import Roster from '../Student/Roster.vue';

defineProps<{
    society: any;
    memberships?: any;
    activeTerm?: any | null;
    currentApplication?: any | null;
}>();
</script>

<template>
    <Roster
        :society="{ ...society, members: memberships?.data ?? society.members ?? [] }"
        section="members"
        :active-term="activeTerm"
        :current-application="currentApplication"
    />
</template>
