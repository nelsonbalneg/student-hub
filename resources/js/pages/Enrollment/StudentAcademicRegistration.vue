<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

type SarStatusResponse = {
  response: 'success' | 'error';
  status?: string;
  term?: string;
  submitted?: boolean;
  message?: string;
};

const agreed = ref(false);
const showTransactionModal = ref(false);
const selectedTransactionType = ref<'Regular' | 'Irregular' | ''>('');
const showSarRecord = ref(false);
const form = useForm({
  transactionType: '',
});

const status = ref('');
const semester = ref('');
const program = ref('');
const transactionType = ref('Enrollment');
const curriculum = ref('');
const dateCreated = ref('');

const notify = (type: 'success' | 'error', text: string) => {
  toast[type](text);
};

const alreadySubmitted = computed(() => {
  const normalized = status.value.toLowerCase();
  return normalized === 'submitted' || normalized === 'enrolled';
});

const statusBadge = computed(() => {
  if (alreadySubmitted.value) return 'SUBMITTED';
  if (!status.value) return 'NOT SUBMITTED';
  return status.value.toUpperCase();
});

const formattedDateCreated = computed(() => {
  if (!dateCreated.value) return '-';
  const parsed = new Date(dateCreated.value);
  if (Number.isNaN(parsed.getTime())) return dateCreated.value;
  return new Intl.DateTimeFormat('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  }).format(parsed);
});


const submitConfirmation = async () => {
  if (form.processing || alreadySubmitted.value || !agreed.value) return;
  showTransactionModal.value = true;
};

const confirmSubmit = () => {
  if (!selectedTransactionType.value || form.processing) return;

  form.transactionType = selectedTransactionType.value;
  form.post('/student-academic-registration/confirm', {
    preserveScroll: true,
    onSuccess: (page) => {
      const flash = (page.props.flash as Record<string, string | undefined>) ?? {};
      const successMessage = flash.success ?? 'Enrollment confirmation submitted successfully.';
      notify('success', successMessage);
      showTransactionModal.value = false;
      selectedTransactionType.value = '';
      showSarRecord.value = true;
      loadStatus();
    },
    onError: (errors) => {
      const errorMessage = (errors?.message as string | undefined) ?? 'Unable to submit confirmation.';
      notify('error', errorMessage);
    },
  });
};

const loadStatus = async () => {
  try {
    const response = await fetch('/student-academic-registration/status', {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    });

    const data = (await response.json()) as SarStatusResponse & { record?: Record<string, unknown> };
    if (!response.ok || data.response !== 'success') return;

    status.value = data.status ?? '';
    semester.value = data.term ?? '';

    const record = data.record ?? {};
    showSarRecord.value = !!status.value;
    program.value = (record.programName as string | undefined) ?? '';
    transactionType.value = (record.transactionType as string | undefined) ?? '-';
    curriculum.value = (record.curriculum as string | undefined) ?? '';
    dateCreated.value = (record.dateCreated as string | undefined) ?? '';
  } catch (_error) {
    // keep page usable if status API fails
  }
};

onMounted(() => {
  loadStatus();
});

</script>

<template>
  <Head title="Student Academic Registration" />

  <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white">Student Academic Registration</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Confirm your enrollment for the upcoming semester.</p>
        </div>
        <div class="flex items-center gap-2">
          <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">{{ statusBadge }}</span>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
      <h2 class="text-lg font-extrabold uppercase tracking-wide text-slate-800 dark:text-slate-100">Important Reminders</h2>
      <ul class="mt-3 list-disc space-y-3 pl-5 text-sm text-slate-700 dark:text-slate-300">
        <li>This is an important reminder about <b class="text-red-700 dark:text-red-300">providing accurate</b> and <b class="text-red-700 dark:text-red-300">truthful information</b> in all profiles and submissions within our academic institution systems. Ensuring the integrity of your data is essential for maintaining the quality and credibility of our educational environment.</li>
        <li>Providing incorrect data <b class="text-red-700 dark:text-red-300">violates</b> our academic policies and can lead to significant issues, including compromised academic integrity and administrative complications.</li>
        <li><b class="text-red-700 dark:text-red-300">Please be aware that taking screenshots of the data in this system is prohibited.</b> Sensitive information must remain secure.</li>
      </ul>
    </section>

    <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-400/30 dark:bg-emerald-500/10">
      <h2 class="text-lg font-extrabold text-emerald-900 dark:text-emerald-200">Enrollment Confirmation</h2>
      <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-emerald-900 dark:text-emerald-100">
        <li>I confirm to enroll at the University of Southern Mindanao for the next semester</li>
        <li>I have reviewed and understood the course offerings, schedules, and academic policies.</li>
        <li>I agree to comply with all the institution's guidelines, deadlines, and financial obligations associated with my enrollment.</li>
      </ul>

      <button
        type="button"
        class="mt-6 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-extrabold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="form.processing || alreadySubmitted || !agreed"
        @click="submitConfirmation"
      >
        {{ alreadySubmitted ? 'ALREADY SUBMITTED' : (form.processing ? 'SUBMITTING...' : 'SUBMIT CONFIRMATION') }}
      </button>

      <label class="mt-4 flex items-center gap-2 text-sm text-emerald-900 dark:text-emerald-100">
        <input v-model="agreed" type="checkbox" class="size-4 rounded border-emerald-400 text-emerald-600" :disabled="alreadySubmitted" />
        I have read and agree to the terms above.
      </label>

    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
      <h2 class="text-lg font-extrabold uppercase tracking-wide text-slate-800 dark:text-slate-100">Current SAR Record</h2>
      <div class="mt-4 grid gap-3 md:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5"><p class="text-xs font-bold uppercase text-slate-500">Status</p><p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ status || '-' }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5"><p class="text-xs font-bold uppercase text-slate-500">Semester</p><p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ semester || '-' }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5"><p class="text-xs font-bold uppercase text-slate-500">Program</p><p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ program || '-' }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5"><p class="text-xs font-bold uppercase text-slate-500">Transaction Type</p><p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ transactionType || '-' }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 md:col-span-2 dark:border-white/10 dark:bg-white/5"><p class="text-xs font-bold uppercase text-slate-500">Curriculum</p><p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ curriculum || '-' }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 md:col-span-2 dark:border-white/10 dark:bg-white/5"><p class="text-xs font-bold uppercase text-slate-500">Date Created</p><p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ formattedDateCreated }}</p></div>
      </div>
    </section>
  </div>

  <div v-if="showTransactionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-white/10 dark:bg-slate-950">
      <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">Select Transaction Type</h3>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose your enrollment transaction type before submitting.</p>

      <div class="mt-4 grid grid-cols-2 gap-2">
        <button
          type="button"
          class="rounded-lg border px-3 py-2 text-sm font-bold"
          :class="selectedTransactionType === 'Regular' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-700 dark:border-white/10 dark:text-slate-200'"
          @click="selectedTransactionType = 'Regular'"
        >
          Regular
        </button>
        <button
          type="button"
          class="rounded-lg border px-3 py-2 text-sm font-bold"
          :class="selectedTransactionType === 'Irregular' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-700 dark:border-white/10 dark:text-slate-200'"
          @click="selectedTransactionType = 'Irregular'"
        >
          Irregular
        </button>
      </div>

      <div class="mt-5 flex justify-end gap-2">
        <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-white/10 dark:text-slate-200" @click="showTransactionModal = false">
          Cancel
        </button>
        <button
          type="button"
          class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-extrabold text-white disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!selectedTransactionType || form.processing"
          @click="confirmSubmit"
        >
          Confirm Submit
        </button>
      </div>
    </div>
  </div>
</template>
