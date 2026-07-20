<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Settings2, Info } from 'lucide-vue-next';
import { update as updateSarSetting } from '@/actions/App/Http/Controllers/SiteSettings/SarSettingController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import { watch } from 'vue';

interface SarSettings {
    sar_enabled: boolean;
}

const props = defineProps<{
    settings: SarSettings;
}>();

const form = useForm({
    sar_enabled: Boolean(props.settings.sar_enabled),
});

const submit = () => {
    form.post(updateSarSetting.url(), {
        preserveScroll: true,
        preserveState: true,
    });
};

const onSwitchToggled = (val: boolean) => {
    form.sar_enabled = val;
    submit();
};

const resetForm = () => {
    form.defaults({
        sar_enabled: Boolean(props.settings.sar_enabled),
    });
    form.reset();
    form.clearErrors();
};

watch(() => props.settings.sar_enabled, (newVal) => {
    form.sar_enabled = Boolean(newVal);
    form.defaults({ sar_enabled: Boolean(newVal) });
});
</script>

<template>
    <Head title="Site Settings - Student Academic Registration" />

    <SiteSettingsLayout>
        <div class="space-y-6 p-4 sm:p-6 lg:p-8 2xl:p-10">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.2em] text-sky-600 uppercase dark:text-sky-400"
                    >
                        Site Settings
                    </p>
                    <h1
                        class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white"
                    >
                        Student Academic Registration
                    </h1>
                    <p
                        class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400"
                    >
                        Manage the availability and behavior of the Student Academic Registration module.
                    </p>
                </div>
            </div>

            <form
                class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_360px]"
                @submit.prevent="submit"
            >
                <section
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="border-b border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-white/10 dark:bg-white/[0.03]"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-10 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 ring-1 ring-sky-500/15 dark:text-sky-300"
                            >
                                <Settings2 class="size-5" />
                            </div>
                            <div>
                                <h2
                                    class="text-sm font-bold text-slate-950 dark:text-white"
                                >
                                    Module Settings
                                </h2>
                                <p
                                    class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Configure core functionality of SAR.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-white/10">
                        <div
                            class="grid gap-3 px-5 py-5 xl:grid-cols-[220px_minmax(0,1fr)]"
                        >
                            <div>
                                <Label
                                    for="sar_enabled"
                                    class="text-xs font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >Enable SAR Module</Label
                                >
                                <p
                                    class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                                >
                                    Toggle the accessibility of the Student Academic Registration feature for all users.
                                </p>
                            </div>
                            <div class="flex items-center h-full">
                                <Switch
                                    id="sar_enabled"
                                    :model-value="form.sar_enabled"
                                    @update:model-value="onSwitchToggled"
                                />
                                <InputError
                                    :message="form.errors.sar_enabled"
                                    class="mt-2"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-white/10 dark:bg-white/[0.03]"
                    >
                        <Info class="size-4 shrink-0 text-sky-500" />
                        <span class="text-xs text-slate-500 dark:text-slate-400"
                            >Changes are applied immediately when toggled.</span
                        >
                    </div>
                </section>
            </form>
        </div>
    </SiteSettingsLayout>
</template>
