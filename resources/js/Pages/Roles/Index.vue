<script setup>
import AdminTableCard from '@/Components/Admin/AdminTableCard.vue';
import AdminTableToolbar from '@/Components/Admin/AdminTableToolbar.vue';
import RowActionMenu from '@/Components/Admin/RowActionMenu.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAdminListActions } from '@/Composables/useAdminListActions';
import { useAdminTableSelection } from '@/Composables/useAdminTableSelection';
import { Head, router } from '@inertiajs/vue3';
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
    canManageRoles: {
        type: Boolean,
        default: false,
    },
});

const rows = computed(() => props.rows);

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

const perPageOptions = [5, 10, 15, 25];

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
    reload,
    refresh,
    confirmDelete,
    confirmBulkDelete,
} = useAdminListActions({
    indexRouteName: 'admin.roles.index',
    destroyRouteName: 'admin.roles.destroy',
    bulkDestroyRouteName: 'admin.roles.bulk-destroy',
    entityLabel: 'Role',
    entityLabelPlural: 'roles',
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

const goToCreatePage = () => {
    router.get(route('admin.roles.create'));
};

const goToEditPage = (role) => {
    router.get(route('admin.roles.edit', role.id));
};

const roleActionItems = (role) => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        command: () => goToEditPage(role),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        disabled: !role.canDelete,
        command: () => confirmDelete(role),
    },
];
</script>

<template>
    <Head title="Roles" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                title="Roles"
                description="Manage application roles with the same admin table standard used across users and permissions."
            />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <DataTable
                    :value="rows"
                    v-model:filters="tableFilters"
                    :rows="tableState.perPage"
                    :first="pagination.first"
                    :totalRecords="pagination.total"
                    :rowsPerPageOptions="perPageOptions"
                    :sortField="tableState.sortField"
                    :sortOrder="tableState.sortOrder"
                    :loading="busy"
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
                                :canCreate="canManageRoles"
                                createLabel="Create Role"
                            :canBulkDelete="canManageRoles"
                            bulkDeleteLabel="Delete Selected"
                            :selectedCount="selectedRows.length"
                            :selectableCount="selectableRows.length"
                            :busy="busy"
                            @create="goToCreatePage"
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
                                No roles found for the current filters.
                            </div>
                        </template>

                        <Column headerStyle="width: 3.5rem" bodyStyle="width: 3.5rem">
                            <template #header>
                            <div :title="selectableRows.length === 0 ? 'No deletable roles on this page.' : ''">
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
                        <template #body="{ data }">
                            <div class="flex flex-wrap items-center gap-2">
                                <span>{{ data.name }}</span>
                                <Tag
                                    v-if="data.deleteBlockCode === 'protected_role'"
                                    value="Protected"
                                    severity="warn"
                                />
                            </div>
                        </template>

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
                            <Tag :value="data.guardName" severity="secondary" />
                        </template>
                    </Column>

                    <Column header="Permissions">
                        <template #body="{ data }">
                            <div v-if="data.permissions?.length" class="flex flex-wrap gap-2">
                                <Tag
                                    v-for="permission in data.permissions.slice(0, 3)"
                                    :key="permission"
                                    :value="permission"
                                    severity="info"
                                />
                                <Tag
                                    v-if="data.permissions.length > 3"
                                    :value="`+${data.permissions.length - 3}`"
                                    severity="contrast"
                                />
                            </div>
                            <span v-else class="text-sm text-slate-500">
                                No permissions
                            </span>
                        </template>
                    </Column>

                        <Column field="usersCount" header="Assigned Users" />
                        <Column field="createdAt" header="Created At" sortable />

                        <Column v-if="canManageRoles" header="Actions" :exportable="false" style="width: 5rem">
                            <template #body="{ data }">
                                <RowActionMenu :items="roleActionItems(data)" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <template #footer>
                    <div class="flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            Showing {{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} of {{ pagination.total }} roles
                        </div>
                        <div>Page {{ pagination.currentPage }} / {{ pagination.lastPage }}</div>
                    </div>
                </template>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
