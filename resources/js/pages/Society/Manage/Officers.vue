<script setup lang="ts">
import Roster from '../Student/Roster.vue';

defineProps<{
    society: any;
    activeTerm?: any | null;
    currentApplication?: any | null;
    officerPositions?: any[];
}>();
</script>

<template>
    <Roster
        :society="society"
        section="officers"
        :active-term="activeTerm"
        :current-application="currentApplication"
        :officer-positions="officerPositions"
    />
</template>
