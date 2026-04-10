import { computed, ref, watch } from 'vue';

export function useAdminTableSelection(rows, options = {}) {
    const {
        getRowId = (row) => row?.id,
        isRowSelectable: isRowSelectableOption = (row) => row?.canDelete !== false,
        autoPrune = true,
    } = options;

    const selectedRows = ref([]);

    const isRowSelectable = (row) => Boolean(row) && isRowSelectableOption(row);

    const selectedIds = computed(() => selectedRows.value
        .map((row) => getRowId(row))
        .filter((id) => id !== undefined && id !== null));

    const selectableRows = computed(() => (rows.value ?? []).filter((row) => isRowSelectable(row)));
    const selectableCount = computed(() => selectableRows.value.length);
    const selectedCount = computed(() => selectedRows.value.length);
    const hasSelection = computed(() => selectedCount.value > 0);

    const allSelected = computed(() => (
        selectableRows.value.length > 0
        && selectableRows.value.every((row) => selectedIds.value.includes(getRowId(row)))
    ));

    const partiallySelected = computed(() => hasSelection.value && !allSelected.value);

    const clearSelection = () => {
        selectedRows.value = [];
    };

    const setSelectedRows = (value) => {
        const nextRows = Array.isArray(value) ? value.filter((row) => isRowSelectable(row)) : [];
        const seen = new Set();

        selectedRows.value = nextRows.filter((row) => {
            const id = getRowId(row);

            if (id === undefined || id === null || seen.has(id)) {
                return false;
            }

            seen.add(id);

            return true;
        });
    };

    const toggleRowSelection = (row) => {
        if (!isRowSelectable(row)) {
            return;
        }

        const rowId = getRowId(row);

        if (selectedIds.value.includes(rowId)) {
            selectedRows.value = selectedRows.value.filter((selectedRow) => getRowId(selectedRow) !== rowId);
            return;
        }

        selectedRows.value = [...selectedRows.value, row];
    };

    const toggleAllSelection = () => {
        if (allSelected.value) {
            clearSelection();
            return;
        }

        selectedRows.value = [...selectableRows.value];
    };

    watch(rows, (nextRows) => {
        if (!autoPrune) {
            return;
        }

        const nextRowMap = new Map((nextRows ?? []).map((row) => [getRowId(row), row]));

        selectedRows.value = selectedRows.value
            .map((row) => nextRowMap.get(getRowId(row)))
            .filter((row) => row && isRowSelectable(row));
    }, { deep: true });

    return {
        selectedRows,
        selectedIds,
        selectableRows,
        selectableCount,
        selectedCount,
        hasSelection,
        allSelected,
        partiallySelected,
        isRowSelectable,
        setSelectedRows,
        clearSelection,
        toggleRowSelection,
        toggleAllSelection,
    };
}
