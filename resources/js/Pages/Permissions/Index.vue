<script setup>
import AdminTableCard from '@/Components/Admin/AdminTableCard.vue';
import AdminTableToolbar from '@/Components/Admin/AdminTableToolbar.vue';
import RowActionMenu from '@/Components/Admin/RowActionMenu.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    adminCurrentPageReportTemplate,
    adminPaginatorTemplate,
    adminRowsPerPageOptions,
} from '@/Constants/adminTablePagination';
import { useAdminListActions } from '@/Composables/useAdminListActions';
import { usePageOverlayCleanup } from '@/Composables/usePageOverlayCleanup';
import { useAdminTableSelection } from '@/Composables/useAdminTableSelection';
import CreateModal from '@/Pages/Permissions/CreateModal.vue';
import EditModal from '@/Pages/Permissions/EditModal.vue';
import { Head } from '@inertiajs/vue3';
import { FilterMatchMode } from '@primevue/core/api';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import ConfirmDialog from 'primevue/confirmdialog';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { computed, reactive, ref } from 'vue';

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
            field: 'name',
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

const tableFilters = ref({
    global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: props.filters.name ?? null, matchMode: FilterMatchMode.CONTAINS },
});

const tableState = reactive({
    page: props.pagination.currentPage,
    perPage: props.pagination.perPage ?? 10,
    sortField: props.sorting.field ?? 'name',
    sortOrder: props.sorting.order ?? 1,
});

const {
    selectedIds,
    selectedRows,
    selectableRows,
    allSelected,
    partiallySelected,
    clearSelection,
    toggleRowSelection,
    toggleAllSelection,
} = useAdminTableSelection(rows);

const buildParams = (overrides = {}) => ({
    global: tableFilters.value.global.value || undefined,
    name: tableFilters.value.name.value || undefined,
    page: tableState.page,
    perPage: tableState.perPage,
    sortField: tableState.sortField || undefined,
    sortOrder: tableState.sortOrder || undefined,
    ...overrides,
});

const {
    busy,
    showSuccess,
    reload,
    refresh,
    confirmDelete,
    confirmBulkDelete,
} = useAdminListActions({
    indexRouteName: 'admin.permissions.index',
    destroyRouteName: 'admin.permissions.destroy',
    bulkDestroyRouteName: 'admin.permissions.bulk-destroy',
    entityLabel: 'Permission',
    entityLabelPlural: 'permissions',
    buildParams,
    clearSelection,
    selectedIds,
    pageState: tableState,
    getCurrentRowCount: () => rows.value.length,
});

const onGlobalFilterInput = (value) => {
    tableFilters.value.global.value = value ?? null;
    tableState.page = 1;
    reload({
        page: 1,
        global: value || undefined,
    }, { resetSelection: true });
};

const onFilter = (event) => {
    tableState.page = 1;

    reload({
        page: 1,
        global: event.filters.global?.value || undefined,
        name: event.filters.name?.value || undefined,
    }, { resetSelection: true });
};

const onSort = (event) => {
    tableState.sortField = event.sortField;
    tableState.sortOrder = event.sortOrder;

    reload({
        sortField: event.sortField,
        sortOrder: event.sortOrder,
    }, { resetSelection: true });
};

const onPage = (event) => {
    tableState.page = event.page + 1;
    tableState.perPage = event.rows;

    reload({
        page: event.page + 1,
        perPage: event.rows,
    }, { resetSelection: true });
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
        label: 'Edit',
        icon: 'pi pi-pencil',
        command: () => openEditModal(permission),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
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
                    <DataTable
                    :value="rows"
                    v-model:filters="tableFilters"
                    :rows="tableState.perPage"
                    :first="pagination.first"
                    :totalRecords="pagination.total"
                    :rowsPerPageOptions="adminRowsPerPageOptions"
                    :sortField="tableState.sortField"
                    :sortOrder="tableState.sortOrder"
                    :loading="busy"
                    :alwaysShowPaginator="true"
                    :paginatorTemplate="adminPaginatorTemplate"
                    :currentPageReportTemplate="adminCurrentPageReportTemplate"
                    class="admin-datatable h-full"
                    data-key="id"
                    paginator
                    lazy
                    scrollable
                    scrollHeight="flex"
                    striped-rows
                    filterDisplay="menu"
                    removableSort
                    responsive-layout="scroll"
                    @filter="onFilter"
                    @sort="onSort"
                    @page="onPage"
                    >
                        <template #header>
                            <AdminTableToolbar
                                :canCreate="canManagePermissions"
                                createLabel="Create Permission"
                            :canBulkDelete="canManagePermissions"
                            bulkDeleteLabel="Delete Selected"
                            :selectedCount="selectedRows.length"
                            :selectableCount="selectableRows.length"
                            :busy="busy"
                            @create="openCreateModal"
                            @bulk-delete="confirmBulkDelete"
                            @refresh="refresh"
                            >
                                <template #search>
                                    <IconField class="w-full">
                                        <InputIcon class="pi pi-search text-slate-400" />
                                        <InputText
                                            v-model="tableFilters.global.value"
                                            placeholder="Global search"
                                            class="w-full"
                                            @update:modelValue="onGlobalFilterInput"
                                        />
                                    </IconField>
                                </template>
                            </AdminTableToolbar>
                        </template>

                        <template #empty>
                            <div class="py-8 text-center text-sm text-slate-500">
                                No permissions found for the current filters.
                            </div>
                        </template>

                        <Column headerStyle="width: 3.5rem" bodyStyle="width: 3.5rem">
                        <template #header>
                            <div :title="selectableRows.length === 0 ? 'No deletable permissions on this page.' : ''">
                                <Checkbox
                                    :binary="true"
                                    :modelValue="allSelected"
                                    :indeterminate="partiallySelected"
                                    :disabled="selectableRows.length === 0"
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

                        <Column v-if="canManagePermissions" header="Actions" :exportable="false" style="width: 5rem">
                            <template #body="{ data }">
                                <RowActionMenu :items="permissionActionItems(data)" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <template #footer>
                    <div class="flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            Showing {{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} of {{ pagination.total }} permissions
                        </div>
                        <div>Page {{ pagination.currentPage }} / {{ pagination.lastPage }}</div>
                    </div>
                </template>
            </AdminTableCard>
        </div>

        <CreateModal
            v-model:visible="isCreateModalOpen"
            @saved="handleSaved"
        />

        <EditModal
            v-model:visible="isEditModalOpen"
            :permission="selectedPermission"
            @saved="handleSaved"
            @update:visible="handleEditVisibilityChange"
        />
    </AuthenticatedLayout>
</template>
