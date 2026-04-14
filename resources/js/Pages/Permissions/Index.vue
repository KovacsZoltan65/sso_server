<script setup>
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import AdminTableSummary from "@/Components/Admin/AdminTableSummary.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import PageHeader from "@/Components/PageHeader.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useAdminListActions } from "@/Composables/useAdminListActions";
import { useAdminTableState } from "@/Composables/useAdminTableState";
import { usePageOverlayCleanup } from "@/Composables/usePageOverlayCleanup";
import { useAdminTableSelection } from "@/Composables/useAdminTableSelection";
import CreateModal from "@/Pages/Permissions/CreateModal.vue";
import EditModal from "@/Pages/Permissions/EditModal.vue";
import { Head } from "@inertiajs/vue3";
import { FilterMatchMode } from "@primevue/core/api";
import Checkbox from "primevue/checkbox";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import InputText from "primevue/inputtext";
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { computed, ref } from "vue";

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            global: null,
            name: null,
        }),
    },
    pagination: {
        type: Object,
        default: () => ({
            currentPage: 1,
            lastPage: 1,
            perPage: 10,
            total: 0,
            from: 0,
            to: 0,
            first: 0,
        }),
    },
    sorting: {
        type: Object,
        default: () => ({
            field: "name",
            order: 1,
        }),
    },
    canManagePermissions: {
        type: Boolean,
        default: false,
    },
});

const rows = computed(() => props.rows);

const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const selectedPermission = ref(null);

const {
    state: tableState,
    filters: tableFilters,
    first,
    lastPage,
    resetPagination,
    setPageFromEvent,
    setSortFromEvent,
    buildFetchParams,
} = useAdminTableState({
    initialPage: props.pagination.currentPage,
    initialPerPage: props.pagination.perPage ?? 10,
    initialSortField: props.sorting.field ?? "name",
    initialSortOrder: props.sorting.order ?? 1,
    initialTotalRecords: props.pagination.total ?? 0,
    initialFilters: {
        global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
        name: { value: props.filters.name ?? null, matchMode: FilterMatchMode.CONTAINS },
    },
    serializeSortOrder: (value) => value,
});

const {
    selectedIds,
    selectedCount,
    selectableCount,
    allSelected,
    partiallySelected,
    clearSelection,
    toggleRowSelection,
    toggleAllSelection,
} = useAdminTableSelection(rows);

const buildParams = (overrides = {}) => buildFetchParams({
    filters: {
        global: tableFilters.global.value || undefined,
        name: tableFilters.name.value || undefined,
    },
    extra: overrides,
});

const {
    busy,
    showSuccess,
    reload,
    refresh,
    confirmDelete,
    confirmBulkDelete,
} = useAdminListActions({
    indexRouteName: "admin.permissions.index",
    destroyRouteName: "admin.permissions.destroy",
    bulkDestroyRouteName: "admin.permissions.bulk-destroy",
    entityLabel: "Permission",
    entityLabelPlural: "permissions",
    buildParams,
    clearSelection,
    selectedIds,
    pageState: tableState,
    getCurrentRowCount: () => rows.value.length,
});

const onGlobalFilterInput = (value) => {
    tableFilters.global.value = value ?? null;
    resetPagination();

    reload(
        buildParams({ page: 1, global: value || undefined }),
        { resetSelection: true }
    );
};

const onFilter = (event) => {
    resetPagination();

    reload(
        buildParams({
            page: 1,
            global: event.filters.global?.value || undefined,
            name: event.filters.name?.value || undefined,
        }),
        { resetSelection: true }
    );
};

const onSort = (event) => {
    setSortFromEvent(event, "name");
    reload(buildParams(), { resetSelection: true });
};

const onPage = (event) => {
    setPageFromEvent(event);
    reload(buildParams(), { resetSelection: true });
};

const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

const openEditModal = (permission) => {
    selectedPermission.value = permission;
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    selectedPermission.value = null;
};

const closeAllOverlays = () => {
    isCreateModalOpen.value = false;
    closeEditModal();
};

usePageOverlayCleanup(closeAllOverlays);

const handleEditVisibilityChange = (value) => {
    if (value) {
        isEditModalOpen.value = true;
        return;
    }

    closeEditModal();
};

const handleSaved = ({ message }) => {
    showSuccess(message);
    clearSelection();
    closeAllOverlays();
    reload({}, { resetSelection: true });
};

const permissionActionItems = (permission) => [
    {
        label: "Edit",
        icon: "pi pi-pencil",
        isPrimary: true,
        command: () => openEditModal(permission),
    },
    {
        label: "Delete",
        icon: "pi pi-trash",
        isDangerous: true,
        disabled: !permission.canDelete,
        command: () => confirmDelete(permission),
    },
];
</script>

<template>
    <Head title="Permissions" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                title="Permissions"
                description="Manage application permissions with the same admin table standard used across users and roles."
            />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <BaseDataTable
                        :value="rows"
                        v-model:filters="tableFilters"
                        :rows="tableState.perPage"
                        :first="first"
                        :total-records="pagination.total"
                        :sort-field="tableState.sortField"
                        :sort-order="tableState.sortOrder"
                        :loading="busy"
                        empty-message="No permissions found for the current filters."
                        loading-message="Loading permissions..."
                        data-key="id"
                        lazy
                        filterDisplay="menu"
                        removableSort
                        responsive-layout="scroll"
                        @filter="onFilter"
                        @sort="onSort"
                        @page="onPage"
                    >
                        <template #header>
                            <AdminTableToolbar
                                searchable
                                :search-value="tableFilters.global.value ?? ''"
                                search-placeholder="Global search"
                                :canCreate="canManagePermissions"
                                createLabel="Create Permission"
                                :canBulkDelete="canManagePermissions"
                                bulkDeleteLabel="Delete Selected"
                                :selectedCount="selectedCount"
                                :selectableCount="selectableCount"
                                :busy="busy"
                                @update:searchValue="onGlobalFilterInput"
                                @create="openCreateModal"
                                @bulk-delete="confirmBulkDelete"
                                @refresh="refresh"
                            />
                        </template>

                        <Column headerStyle="width: 3.5rem" bodyStyle="width: 3.5rem">
                            <template #header>
                                <div
                                    :title="
                                        selectableCount === 0
                                            ? 'No deletable permissions on this page.'
                                            : ''
                                    "
                                >
                                    <Checkbox
                                        :binary="true"
                                        :modelValue="allSelected"
                                        :indeterminate="partiallySelected"
                                        :disabled="selectableCount === 0"
                                        @update:modelValue="toggleAllSelection"
                                    />
                                </div>
                            </template>

                            <template #body="{ data }">
                                <div :title="data.deleteBlockReason ?? ''">
                                    <Checkbox
                                        :binary="true"
                                        :modelValue="selectedIds.includes(data.id)"
                                        :disabled="!data.canDelete"
                                        @update:modelValue="toggleRowSelection(data)"
                                    />
                                </div>
                            </template>
                        </Column>

                        <Column
                            field="name"
                            header="Name"
                            sortable
                            :showFilterMatchModes="false"
                            :showFilterOperator="false"
                            :showAddButton="false"
                        >
                            <template #filter="{ filterModel, filterCallback }">
                                <InputText
                                    v-model="filterModel.value"
                                    placeholder="Filter name"
                                    class="w-full"
                                    @input="filterCallback()"
                                />
                            </template>
                        </Column>

                        <Column field="guardName" header="Guard">
                            <template #body="{ data }">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Tag :value="data.guardName" severity="secondary" />
                                    <Tag
                                        v-if="data.deleteBlockCode === 'assigned_records'"
                                        value="In Use"
                                        severity="warn"
                                    />
                                </div>
                            </template>
                        </Column>

                        <Column field="rolesCount" header="Assigned Roles" />
                        <Column field="usersCount" header="Direct Users" />
                        <Column field="createdAt" header="Created At" sortable />

                        <Column
                            v-if="canManagePermissions"
                            header="Actions"
                            :exportable="false"
                            style="width: 12rem"
                        >
                            <template #body="{ data }">
                                <RowActionMenu :items="permissionActionItems(data)" />
                            </template>
                        </Column>
                    </BaseDataTable>
                </div>

                <template #footer>
                    <AdminTableSummary
                        :page="tableState.page"
                        :per-page="tableState.perPage"
                        :total="pagination.total"
                        :from="pagination.from"
                        :to="pagination.to"
                        :current-page="pagination.currentPage"
                        :last-page="pagination.lastPage || lastPage"
                        item-label="permissions"
                    />
                </template>
            </AdminTableCard>
        </div>

        <CreateModal v-model:visible="isCreateModalOpen" @saved="handleSaved" />

        <EditModal
            v-model:visible="isEditModalOpen"
            :permission="selectedPermission"
            @saved="handleSaved"
            @update:visible="handleEditVisibilityChange"
        />
    </AuthenticatedLayout>
</template>
