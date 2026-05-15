<script setup lang="ts">
import Roster from '../Student/Roster.vue';

defineProps<{
    society: any;
    activeTerm?: any | null;
    currentApplication?: any | null;
}>();
</script>

<template>
    <Roster
        :society="society"
        section="advisers"
        :active-term="activeTerm"
        :current-application="currentApplication"
    />
</template>
