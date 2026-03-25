import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, watch } from 'vue';

export function usePageOverlayCleanup(cleanup) {
    const page = usePage();

    const runCleanup = () => {
        cleanup?.();
    };

    watch(
        () => page.url,
        () => {
            runCleanup();
        },
    );

    onBeforeUnmount(() => {
        runCleanup();
    });

    return {
        runCleanup,
    };
}
