<template>
    <div class="group flex items-center gap-3">
        <div
            class="relative flex aspect-square size-10 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-[0_16px_35px_-18px_rgba(36,58,120,0.6)] ring-1 ring-slate-200/80 dark:ring-slate-700"
        >
            <div
                class="absolute inset-1 rounded-xl bg-gradient-to-br from-sky-100 via-white to-rose-100"
            />
            <div
                class="absolute top-2 left-2 size-4 rounded-full bg-sky-400/85 blur-[1px]"
            />
            <div
                class="absolute right-2 bottom-2 size-4 rounded-full bg-rose-400/85 blur-[1px]"
            />
            <span
                class="relative text-base leading-none font-black text-slate-900"
                >S</span
            >
        </div>
        <div class="flex min-w-0 flex-col group-data-[collapsible=icon]:hidden">
            <span
                class="truncate text-[15px] leading-none font-bold text-slate-950 dark:text-white"
                >Student Hub</span
            >
            <span
                class="mt-1 truncate text-xs leading-none font-medium text-slate-500 dark:text-slate-400"
                >academic portal</span
            >
        </div>
    </div>
</template>
