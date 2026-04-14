import { onBeforeUnmount } from 'vue';

export function useAdminSearchBehavior(options = {}) {
    const {
        debounceMs = 350,
    } = options;

    let debounceTimer = null;

    const clearPendingSearch = () => {
        if (debounceTimer !== null) {
            window.clearTimeout(debounceTimer);
            debounceTimer = null;
        }
    };

    const queueSearch = (callback) => {
        clearPendingSearch();
        debounceTimer = window.setTimeout(() => {
            debounceTimer = null;
            callback?.();
        }, debounceMs);
    };

    const submitSearch = (callback) => {
        clearPendingSearch();
        callback?.();
    };

    const applyFilterChange = (callback) => {
        clearPendingSearch();
        callback?.();
    };

    onBeforeUnmount(() => {
        clearPendingSearch();
    });

    return {
        clearPendingSearch,
        queueSearch,
        submitSearch,
        applyFilterChange,
    };
}
