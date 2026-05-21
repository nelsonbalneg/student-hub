import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

type InertiaSuccessEvent = CustomEvent<{
    page?: {
        props?: {
            flash?: {
                toast?: FlashToast;
            };
        };
    };
}>;

export function initializeFlashToast(): void {
    router.on('success', (event) => {
        const data = (event as InertiaSuccessEvent).detail?.page?.props?.flash
            ?.toast;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });
}
