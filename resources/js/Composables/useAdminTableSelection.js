import { computed, ref, watch } from 'vue';

export function useAdminTableSelection(rows) {
    const selectedIds = ref([]);

    const selectableRows = computed(() => rows.value.filter((row) => row.canDelete !== false));

    const selectedRows = computed(() => rows.value.filter((row) => selectedIds.value.includes(row.id)));

    const allSelected = computed(() => (
        selectableRows.value.length > 0
        && selectableRows.value.every((row) => selectedIds.value.includes(row.id))
    ));

    const partiallySelected = computed(() => (
        selectedIds.value.length > 0
        && !allSelected.value
    ));

    const clearSelection = () => {
        selectedIds.value = [];
    };

    const toggleRowSelection = (row) => {
        if (row.canDelete === false) {
            return;
        }

        if (selectedIds.value.includes(row.id)) {
            selectedIds.value = selectedIds.value.filter((id) => id !== row.id);

            return;
        }

        selectedIds.value = [...selectedIds.value, row.id];
    };

    const toggleAllSelection = () => {
        if (allSelected.value) {
            clearSelection();

            return;
        }

        selectedIds.value = selectableRows.value.map((row) => row.id);
    };

    watch(rows, (nextRows) => {
        const currentRowIds = new Set(nextRows.map((row) => row.id));
        selectedIds.value = selectedIds.value.filter((id) => currentRowIds.has(id));
    });

    return {
        selectedIds,
        selectedRows,
        selectableRows,
        allSelected,
        partiallySelected,
        clearSelection,
        toggleRowSelection,
        toggleAllSelection,
    };
}
