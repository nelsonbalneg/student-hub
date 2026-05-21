<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Building2,
    ImagePlus,
    Info,
    RefreshCw,
    Save,
    ShieldCheck,
    Sparkles,
    Trash2,
    UploadCloud,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { update as updateBranding } from '@/actions/App/Http/Controllers/SiteSettings/SiteBrandingController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';

interface BrandingSettings {
    site_name: string;
    site_tagline: string | null;
    site_footer_name: string | null;
    site_logo: string | null;
    site_logo_url: string | null;
    site_favicon: string | null;
    site_favicon_url: string | null;
}

const props = defineProps<{
    settings: BrandingSettings;
}>();

const logoInput = ref<HTMLInputElement | null>(null);
const faviconInput = ref<HTMLInputElement | null>(null);
const logoPreview = ref<string | null>(props.settings.site_logo_url);
const faviconPreview = ref<string | null>(props.settings.site_favicon_url);

const form = useForm({
    site_name: props.settings.site_name || 'ONE USM',
    site_tagline:
        props.settings.site_tagline ||
        'Connecting You to the Digital USM Experience.',
    site_footer_name:
        props.settings.site_footer_name ||
        'ONE USM SSO Facility • Secure • Unified • Connected',
    site_logo: null as File | null,
    site_favicon: null as File | null,
    remove_logo: false,
    remove_favicon: false,
});

const previewName = computed(() => form.site_name || 'ONE USM');
const previewTagline = computed(
    () => form.site_tagline || 'Connecting You to the Digital USM Experience.',
);
const previewFooter = computed(
    () =>
        form.site_footer_name ||
        'ONE USM SSO Facility • Secure • Unified • Connected',
);

const readPreview = (file: File, target: typeof logoPreview) => {
    const reader = new FileReader();
    reader.onload = () => {
        target.value = typeof reader.result === 'string' ? reader.result : null;
    };
    reader.readAsDataURL(file);
};

const selectLogo = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.site_logo = file;
    form.remove_logo = false;

    if (file) {
        readPreview(file, logoPreview);
    }
};

const selectFavicon = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.site_favicon = file;
    form.remove_favicon = false;

    if (file) {
        readPreview(file, faviconPreview);
    }
};

const removeLogo = () => {
    form.site_logo = null;
    form.remove_logo = true;
    logoPreview.value = null;

    if (logoInput.value) {
        logoInput.value.value = '';
    }
};

const removeFavicon = () => {
    form.site_favicon = null;
    form.remove_favicon = true;
    faviconPreview.value = null;

    if (faviconInput.value) {
        faviconInput.value.value = '';
    }
};

const resetForm = () => {
    form.defaults({
        site_name: props.settings.site_name || 'ONE USM',
        site_tagline:
            props.settings.site_tagline ||
            'Connecting You to the Digital USM Experience.',
        site_footer_name:
            props.settings.site_footer_name ||
            'ONE USM SSO Facility • Secure • Unified • Connected',
        site_logo: null,
        site_favicon: null,
        remove_logo: false,
        remove_favicon: false,
    });
    form.reset();
    form.clearErrors();
    logoPreview.value = props.settings.site_logo_url;
    faviconPreview.value = props.settings.site_favicon_url;

    if (logoInput.value) {
        logoInput.value.value = '';
    }

    if (faviconInput.value) {
        faviconInput.value.value = '';
    }
};

const submit = () => {
    form.post(updateBranding.url(), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.site_logo = null;
            form.site_favicon = null;
            form.remove_logo = false;
            form.remove_favicon = false;

            const faviconHref = faviconPreview.value;

            if (faviconHref) {
                document
                    .querySelectorAll<HTMLLinkElement>(
                        'link[rel~="icon"], link[rel="apple-touch-icon"]',
                    )
                    .forEach((link) => {
                        link.href = `${faviconHref}${faviconHref.includes('?') ? '&' : '?'}v=${Date.now()}`;
                    });
            }
        },
    });
};
</script>

<template>
    <Head title="Site Settings - Branding" />

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
                        Branding & Details
                    </h1>
                    <p
                        class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400"
                    >
                        Manage the application identity used across headers,
                        login screens, browser tabs, emails, and selected
                        reports.
                    </p>
                </div>
                <div
                    class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300"
                >
                    <ShieldCheck class="size-3.5" />
                    Managed branding
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
                                <Sparkles class="size-5" />
                            </div>
                            <div>
                                <h2
                                    class="text-sm font-bold text-slate-950 dark:text-white"
                                >
                                    Branding & Details
                                </h2>
                                <p
                                    class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Core platform labels and image assets.
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
                                    for="site_name"
                                    class="text-xs font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >Site Name</Label
                                >
                                <p
                                    class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                                >
                                    Main system/application name displayed in
                                    headers, browser titles, login pages, and
                                    branding areas.
                                </p>
                            </div>
                            <div>
                                <Input
                                    id="site_name"
                                    v-model="form.site_name"
                                    class="bg-white dark:bg-slate-900"
                                    placeholder="ONE USM"
                                />
                                <InputError
                                    :message="form.errors.site_name"
                                    class="mt-2"
                                />
                            </div>
                        </div>

                        <div
                            class="grid gap-3 px-5 py-5 xl:grid-cols-[220px_minmax(0,1fr)]"
                        >
                            <div>
                                <Label
                                    for="site_tagline"
                                    class="text-xs font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >Tagline</Label
                                >
                                <p
                                    class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                                >
                                    Short branding statement displayed on login
                                    pages, welcome screens, and selected public
                                    pages.
                                </p>
                            </div>
                            <div>
                                <Input
                                    id="site_tagline"
                                    v-model="form.site_tagline"
                                    class="bg-white dark:bg-slate-900"
                                    placeholder="Connecting You to the Digital USM Experience."
                                />
                                <InputError
                                    :message="form.errors.site_tagline"
                                    class="mt-2"
                                />
                            </div>
                        </div>

                        <div
                            class="grid gap-3 px-5 py-5 xl:grid-cols-[220px_minmax(0,1fr)]"
                        >
                            <div>
                                <Label
                                    for="site_footer_name"
                                    class="text-xs font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >Footer Name</Label
                                >
                                <p
                                    class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                                >
                                    Displayed in the footer of emails and
                                    selected pages throughout the platform.
                                </p>
                            </div>
                            <div>
                                <textarea
                                    id="site_footer_name"
                                    v-model="form.site_footer_name"
                                    rows="3"
                                    class="min-h-24 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white"
                                    placeholder="ONE USM SSO Facility • Secure • Unified • Connected"
                                />
                                <InputError
                                    :message="form.errors.site_footer_name"
                                    class="mt-2"
                                />
                            </div>
                        </div>

                        <div
                            class="grid gap-3 px-5 py-5 xl:grid-cols-[220px_minmax(0,1fr)]"
                        >
                            <div>
                                <Label
                                    class="text-xs font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >Site Logo</Label
                                >
                                <p
                                    class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                                >
                                    Used for sidebar, login branding,
                                    navigation, and report branding where
                                    available. PNG, JPG, SVG, and WEBP are
                                    accepted.
                                </p>
                            </div>
                            <div
                                class="grid gap-4 md:grid-cols-[180px_minmax(0,1fr)]"
                            >
                                <div
                                    class="flex min-h-32 items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]"
                                >
                                    <img
                                        v-if="logoPreview"
                                        :src="logoPreview"
                                        alt="Current site logo"
                                        class="max-h-24 max-w-full object-contain"
                                    />
                                    <div
                                        v-else
                                        class="flex flex-col items-center gap-2 text-slate-400"
                                    >
                                        <ImagePlus class="size-8" />
                                        <span class="text-xs font-bold"
                                            >No logo</span
                                        >
                                    </div>
                                </div>
                                <div class="flex flex-col justify-center gap-3">
                                    <input
                                        ref="logoInput"
                                        type="file"
                                        accept=".png,.jpg,.jpeg,.svg,.webp"
                                        class="hidden"
                                        @change="selectLogo"
                                    />
                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="gap-2"
                                            @click="logoInput?.click()"
                                        >
                                            <UploadCloud class="size-4" />
                                            Change Logo
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            class="gap-2 text-red-600 hover:text-red-700 dark:text-red-400"
                                            @click="removeLogo"
                                        >
                                            <Trash2 class="size-4" />
                                            Remove
                                        </Button>
                                    </div>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Recommended: transparent SVG or PNG, up
                                        to 3 MB.
                                    </p>
                                    <InputError
                                        :message="form.errors.site_logo"
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            class="grid gap-3 px-5 py-5 xl:grid-cols-[220px_minmax(0,1fr)]"
                        >
                            <div>
                                <Label
                                    class="text-xs font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >Favicon</Label
                                >
                                <p
                                    class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                                >
                                    Browser tab and mobile shortcut icon. ICO,
                                    PNG, and SVG are accepted.
                                </p>
                            </div>
                            <div
                                class="grid gap-4 md:grid-cols-[120px_minmax(0,1fr)]"
                            >
                                <div
                                    class="flex min-h-28 items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]"
                                >
                                    <img
                                        v-if="faviconPreview"
                                        :src="faviconPreview"
                                        alt="Current favicon"
                                        class="size-14 object-contain"
                                    />
                                    <Building2
                                        v-else
                                        class="size-8 text-slate-400"
                                    />
                                </div>
                                <div class="flex flex-col justify-center gap-3">
                                    <input
                                        ref="faviconInput"
                                        type="file"
                                        accept=".ico,.png,.svg"
                                        class="hidden"
                                        @change="selectFavicon"
                                    />
                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="gap-2"
                                            @click="faviconInput?.click()"
                                        >
                                            <UploadCloud class="size-4" />
                                            Change Favicon
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            class="gap-2 text-red-600 hover:text-red-700 dark:text-red-400"
                                            @click="removeFavicon"
                                        >
                                            <Trash2 class="size-4" />
                                            Remove
                                        </Button>
                                    </div>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Recommended: square icon, 512x512 PNG or
                                        SVG, up to 1 MB.
                                    </p>
                                    <InputError
                                        :message="form.errors.site_favicon"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-white/[0.03]"
                    >
                        <div
                            class="flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400"
                        >
                            <Info class="mt-0.5 size-4 shrink-0 text-sky-500" />
                            <span
                                >Changes are applied globally after saving. The
                                browser favicon refreshes automatically.</span
                            >
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="gap-2"
                                :disabled="form.processing"
                                @click="resetForm"
                            >
                                <RefreshCw class="size-4" />
                                Reset
                            </Button>
                            <Button
                                type="submit"
                                class="gap-2 bg-sky-600 text-white shadow-lg shadow-sky-500/20 hover:bg-sky-700"
                                :disabled="form.processing"
                            >
                                <Save class="size-4" />
                                {{
                                    form.processing
                                        ? 'Saving...'
                                        : 'Save Changes'
                                }}
                            </Button>
                        </div>
                    </div>
                </section>

                <aside class="space-y-4">
                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <p
                            class="text-xs font-bold tracking-[0.18em] text-slate-400 uppercase"
                        >
                            Live Preview
                        </p>
                        <div
                            class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-12 items-center justify-center rounded-xl bg-white ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-white/10"
                                >
                                    <img
                                        v-if="logoPreview"
                                        :src="logoPreview"
                                        alt=""
                                        class="max-h-8 max-w-8 object-contain"
                                    />
                                    <Building2
                                        v-else
                                        class="size-5 text-sky-500"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <h3
                                        class="truncate text-sm font-bold text-slate-950 dark:text-white"
                                    >
                                        {{ previewName }}
                                    </h3>
                                    <p
                                        class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{ previewTagline }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="mt-5 rounded-xl bg-white p-3 text-xs font-medium text-slate-500 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-400 dark:ring-white/10"
                            >
                                {{ previewFooter }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 dark:border-emerald-500/20 dark:bg-emerald-500/10"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                            >
                                <ShieldCheck class="size-5" />
                            </div>
                            <div>
                                <h3
                                    class="text-sm font-bold text-emerald-950 dark:text-emerald-100"
                                >
                                    Branding scope
                                </h3>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-emerald-800/80 dark:text-emerald-100/70"
                                >
                                    These values are exposed as shared
                                    application settings and can be reused by
                                    headers, authentication screens, reports,
                                    and email templates.
                                </p>
                            </div>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </SiteSettingsLayout>
</template>
