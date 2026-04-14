import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';

export function useAdminListActions({
    indexRouteName,
    destroyRouteName,
    bulkDestroyRouteName,
    entityLabel,
    entityLabelPlural,
    buildParams,
    clearSelection,
    selectedIds,
    pageState = null,
    getCurrentRowCount = null,
}) {
    const confirm = useConfirm();
    const toast = useToast();
    const page = usePage();
    const isReloading = ref(false);
    const isMutating = ref(false);

    const busy = computed(() => isReloading.value || isMutating.value);

    const closeConfirm = () => {
        confirm.close?.();
    };

    watch(
        () => page.url,
        () => {
            closeConfirm();
        },
    );

    onBeforeUnmount(() => {
        closeConfirm();
    });

    const showSuccess = (message) => {
        toast.add({
            severity: 'success',
            summary: trans('common.success'),
            detail: message,
            life: 3000,
        });
    };

    const showError = (message) => {
        toast.add({
            severity: 'error',
            summary: trans('common.error'),
            detail: message,
            life: 4000,
        });
    };

    const reload = (overrides = {}, options = {}) => {
        const {
            resetSelection = false,
        } = options;

        if (resetSelection) {
            clearSelection();
        }

        closeConfirm();

        router.get(route(indexRouteName), buildParams(overrides), {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            onStart: () => {
                isReloading.value = true;
            },
            onFinish: () => {
                isReloading.value = false;
            },
        });
    };

    const refresh = () => {
        reload({}, { resetSelection: true });
        showSuccess(trans('common.refresh'));
    };

    const extractErrorMessage = (error, fallbackMessage) => (
        error.response?.data?.message
        ?? error.response?.data?.errors?.ids?.[0]
        ?? error.response?.data?.errors?.user?.[0]
        ?? error.response?.data?.errors?.role?.[0]
        ?? error.response?.data?.errors?.permission?.[0]
        ?? fallbackMessage
    );

    const deleteRequest = async (url, payload = undefined) => {
        isMutating.value = true;
        closeConfirm();

        try {
            const response = await axios.delete(url, payload ? { data: payload } : undefined);
            const deletedCount = Number(response.data?.meta?.deletedCount ?? payload?.ids?.length ?? 1);
            const currentRowCount = typeof getCurrentRowCount === 'function' ? getCurrentRowCount() : null;
            const shouldGoToPreviousPage = Boolean(
                pageState
                && typeof currentRowCount === 'number'
                && pageState.page > 1
                && deletedCount >= currentRowCount,
            );

            if (shouldGoToPreviousPage) {
                pageState.page -= 1;
            }

            clearSelection();
            showSuccess(response.data.message);
            reload(shouldGoToPreviousPage ? { page: pageState.page } : {}, { resetSelection: true });
        } catch (error) {
            showError(extractErrorMessage(error, trans('common.error')));
        } finally {
            isMutating.value = false;
        }
    };

    const confirmDelete = (row) => {
        confirm.require({
            message: `${trans('common.delete')} "${row.name}"?`,
            header: `${trans('common.delete')} ${entityLabel}`,
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: trans('common.delete'),
            rejectLabel: trans('common.cancel'),
            acceptClass: 'p-button-danger',
            accept: () => {
                closeConfirm();
                deleteRequest(route(destroyRouteName, row.id));
            },
        });
    };

    const confirmBulkDelete = () => {
        confirm.require({
            message: `${trans('common.delete')} (${selectedIds.value.length}) ${entityLabelPlural}?`,
            header: `${trans('common.delete')} ${entityLabelPlural}`,
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: trans('common.delete'),
            rejectLabel: trans('common.cancel'),
            acceptClass: 'p-button-danger',
            accept: () => {
                closeConfirm();
                deleteRequest(route(bulkDestroyRouteName), {
                    ids: selectedIds.value,
                });
            },
        });
    };

    return {
        busy,
        showSuccess,
        showError,
        reload,
        refresh,
        confirmDelete,
        confirmBulkDelete,
    };
}
