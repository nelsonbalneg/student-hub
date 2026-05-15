<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ application: any; type: string; generatedAt: string }>();
</script>

<template>
    <Head title="Print Accreditation" />

    <div class="bg-white p-10 text-slate-950 print:p-0">
        <div class="mx-auto max-w-4xl space-y-8">
            <header class="border-b-2 border-slate-900 pb-4 text-center">
                <p class="text-sm font-bold uppercase">University of Southern Mindanao · Office of Student Affairs</p>
                <h1 class="mt-2 text-2xl font-black uppercase">Registration/Accreditation of Student Campus Organizations</h1>
                <p class="text-sm font-semibold">USM-OSA-F06-Rev.02.2025.05.05</p>
            </header>

            <section class="grid grid-cols-2 gap-4 text-sm">
                <p><strong>Request No:</strong> {{ application.accreditation_request_no ?? 'Unassigned' }}</p>
                <p><strong>Status:</strong> {{ application.status }}</p>
                <p><strong>Society:</strong> {{ application.society?.full_name ?? application.society?.name }}</p>
                <p><strong>Term:</strong> {{ application.semester }} {{ application.school_year }}</p>
                <p><strong>Submitted by:</strong> {{ application.submitted_by_name }} / {{ application.submitted_by_position }}</p>
                <p><strong>Generated:</strong> {{ generatedAt }}</p>
            </section>

            <section v-if="['summary', 'officers'].includes(type)">
                <h2 class="mb-3 text-lg font-black uppercase">Officers and Adviser/s</h2>
                <table class="w-full border-collapse text-sm">
                    <thead><tr><th class="border p-2 text-left">Position</th><th class="border p-2 text-left">Name</th><th class="border p-2 text-left">Email</th><th class="border p-2 text-left">Contact</th></tr></thead>
                    <tbody>
                        <tr v-for="officer in application.officers" :key="`o-${officer.id}`"><td class="border p-2">{{ officer.position }}</td><td class="border p-2">{{ officer.full_name }}</td><td class="border p-2">{{ officer.usm_email }}</td><td class="border p-2">{{ officer.contact_no }}</td></tr>
                        <tr v-for="adviser in application.advisers" :key="`a-${adviser.id}`"><td class="border p-2">Adviser</td><td class="border p-2">{{ adviser.full_name }}</td><td class="border p-2">{{ adviser.usm_email }}</td><td class="border p-2">{{ adviser.college_unit }}</td></tr>
                    </tbody>
                </table>
            </section>

            <section v-if="['summary', 'members'].includes(type)">
                <h2 class="mb-3 text-lg font-black uppercase">Members Summary</h2>
                <p class="text-sm">Total encoded members: <strong>{{ application.members.length }}</strong></p>
            </section>

            <section v-if="['summary', 'requirements'].includes(type)">
                <h2 class="mb-3 text-lg font-black uppercase">Requirements Checklist</h2>
                <table class="w-full border-collapse text-sm">
                    <thead><tr><th class="border p-2 text-left">Requirement</th><th class="border p-2 text-left">Status</th><th class="border p-2 text-left">Remarks</th></tr></thead>
                    <tbody><tr v-for="submission in application.submissions" :key="submission.id"><td class="border p-2">{{ submission.requirement?.name }}</td><td class="border p-2">{{ submission.status }}</td><td class="border p-2">{{ submission.remarks }}</td></tr></tbody>
                </table>
            </section>

            <section v-if="['summary', 'commitment'].includes(type)">
                <h2 class="mb-3 text-lg font-black uppercase">Adviser Commitment</h2>
                <p class="text-sm leading-7">The adviser/s commit to supervise the organization, attend important meetings and activities, prohibit hazing, observe membership limitations, and accept responsibility for the welfare of members and applicants.</p>
            </section>

            <section v-if="['summary', 'certificate'].includes(type)" class="border-4 border-double border-slate-900 p-8 text-center">
                <h2 class="text-2xl font-black uppercase">Accreditation Approval Summary</h2>
                <p class="mt-4 text-sm">This certifies that the society named above has completed the OSA accreditation workflow for the selected semester and school year, subject to University policies.</p>
            </section>
        </div>
    </div>
</template>
