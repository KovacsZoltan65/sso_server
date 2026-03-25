import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
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
            summary: 'Sikeres művelet',
            detail: message,
            life: 3000,
        });
    };

    const showError = (message) => {
        toast.add({
            severity: 'error',
            summary: 'Hiba',
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
        showSuccess(`${entityLabelPlural} refreshed successfully.`);
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

            clearSelection();
            showSuccess(response.data.message);
            reload({}, { resetSelection: true });
        } catch (error) {
            showError(extractErrorMessage(error, `The selected ${entityLabel} operation could not be completed.`));
        } finally {
            isMutating.value = false;
        }
    };

    const confirmDelete = (row) => {
        confirm.require({
            message: `Delete "${row.name}"? This action cannot be undone.`,
            header: `Delete ${entityLabel}`,
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: 'Delete',
            rejectLabel: 'Cancel',
            acceptClass: 'p-button-danger',
            accept: () => {
                closeConfirm();
                deleteRequest(route(destroyRouteName, row.id));
            },
        });
    };

    const confirmBulkDelete = () => {
        confirm.require({
            message: `Delete the selected ${selectedIds.value.length} ${entityLabelPlural}? This action cannot be undone.`,
            header: `Delete ${entityLabelPlural}`,
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: 'Delete',
            rejectLabel: 'Cancel',
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
