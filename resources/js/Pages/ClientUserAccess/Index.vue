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
import { useAdminTableSelection } from '@/Composables/useAdminTableSelection';
import CreateDialog from '@/Pages/ClientUserAccess/components/CreateDialog.vue';
import EditDialog from '@/Pages/ClientUserAccess/components/EditDialog.vue';
import { Head } from '@inertiajs/vue3';
import { FilterMatchMode } from '@primevue/core/api';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import ConfirmDialog from 'primevue/confirmdialog';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    clientOptions: {
        type: Array,
        default: () => [],
    },
    userOptions: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            global: null,
            client_id: null,
            user_id: null,
            status: null,
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
            field: 'createdAt',
            order: -1,
        }),
    },
    canManageClientAccess: {
        type: Boolean,
        default: false,
    },
});

const rows = computed(() => props.rows);
const createVisible = ref(false);
const editVisible = ref(false);
const selectedAccess = ref(null);

const tableFilters = ref({
    global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
    client_id: { value: props.filters.client_id ?? null, matchMode: FilterMatchMode.EQUALS },
    user_id: { value: props.filters.user_id ?? null, matchMode: FilterMatchMode.EQUALS },
    status: { value: props.filters.status ?? null, matchMode: FilterMatchMode.EQUALS },
});

const tableState = reactive({
    page: props.pagination.currentPage,
    perPage: props.pagination.perPage ?? 10,
    sortField: props.sorting.field ?? 'createdAt',
    sortOrder: props.sorting.order ?? -1,
});

const statusOptions = [
    { label: 'All', value: null },
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

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
    client_id: tableFilters.value.client_id.value || undefined,
    user_id: tableFilters.value.user_id.value || undefined,
    status: tableFilters.value.status.value || undefined,
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
    indexRouteName: 'admin.client-user-access.index',
    destroyRouteName: 'api.client-user-access.destroy',
    bulkDestroyRouteName: 'api.client-user-access.bulk-destroy',
    entityLabel: 'Client access',
    entityLabelPlural: 'client access records',
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

const onSelectFilterChange = () => {
    tableState.page = 1;

    reload({
        page: 1,
        client_id: tableFilters.value.client_id.value || undefined,
        user_id: tableFilters.value.user_id.value || undefined,
        status: tableFilters.value.status.value || undefined,
    }, { resetSelection: true });
};

const openCreateDialog = () => {
    createVisible.value = true;
};

const openEditDialog = (access) => {
    selectedAccess.value = access;
    editVisible.value = true;
};

const closeEditDialog = () => {
    editVisible.value = false;
    selectedAccess.value = null;
};

const handleSaved = ({ message }) => {
    showSuccess(message);
    clearSelection();
    createVisible.value = false;
    closeEditDialog();
    reload({}, { resetSelection: true });
};

const actionItems = (access) => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        command: () => openEditDialog(access),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        disabled: !access.canDelete,
        command: () => confirmDelete({
            id: access.id,
            name: `${access.clientName} -> ${access.userName}`,
        }),
    },
];

const formatDate = (value) => value ? String(value).replace('T', ' ').slice(0, 16) : 'Not set';
</script>

<template>
    <Head title="Client Access" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                title="Client Access"
                description="Control which authenticated users may authorize against each SSO client, including time-bounded assignments."
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
                        striped-rows
                        responsive-layout="scroll"
                        @sort="onSort"
                        @page="onPage"
                    >
                        <template #header>
                            <AdminTableToolbar
                                :canCreate="canManageClientAccess"
                                createLabel="Create Access"
                                :canBulkDelete="canManageClientAccess"
                                bulkDeleteLabel="Delete Selected"
                                :selectedCount="selectedRows.length"
                                :selectableCount="selectableRows.length"
                                :busy="busy"
                                @create="openCreateDialog"
                                @bulk-delete="confirmBulkDelete"
                                @refresh="refresh"
                            >
                                <template #search>
                                    <div class="grid gap-3 md:grid-cols-4">
                                        <IconField class="w-full md:col-span-1">
                                            <InputIcon class="pi pi-search text-slate-400" />
                                            <InputText
                                                v-model="tableFilters.global.value"
                                                placeholder="Search access"
                                                class="w-full"
                                                @update:modelValue="onGlobalFilterInput"
                                            />
                                        </IconField>
                                        <Select
                                            v-model="tableFilters.client_id.value"
                                            :options="clientOptions"
                                            optionLabel="name"
                                            optionValue="id"
                                            placeholder="All clients"
                                            class="w-full"
                                            @change="onSelectFilterChange"
                                        />
                                        <Select
                                            v-model="tableFilters.user_id.value"
                                            :options="userOptions"
                                            optionLabel="name"
                                            optionValue="id"
                                            placeholder="All users"
                                            class="w-full"
                                            @change="onSelectFilterChange"
                                        />
                                        <Select
                                            v-model="tableFilters.status.value"
                                            :options="statusOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            placeholder="All statuses"
                                            class="w-full"
                                            @change="onSelectFilterChange"
                                        />
                                    </div>
                                </template>
                            </AdminTableToolbar>
                        </template>

                        <template #empty>
                            <div class="py-8 text-center text-sm text-slate-500">
                                No client access records found for the current filters.
                            </div>
                        </template>

                        <Column headerStyle="width: 3.5rem" bodyStyle="width: 3.5rem">
                            <template #header>
                                <div :title="selectableRows.length === 0 ? 'No deletable access records on this page.' : ''">
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
                                <Checkbox
                                    :binary="true"
                                    :modelValue="selectedIds.includes(data.id)"
                                    :disabled="!data.canDelete"
                                    @update:modelValue="toggleRowSelection(data)"
                                />
                            </template>
                        </Column>

                        <Column field="clientName" header="Client" sortable>
                            <template #body="{ data }">
                                <div class="space-y-1">
                                    <div class="font-medium text-slate-800">{{ data.clientName }}</div>
                                    <div class="font-mono text-xs text-slate-500">{{ data.clientPublicId }}</div>
                                </div>
                            </template>
                        </Column>

                        <Column field="userName" header="User" sortable>
                            <template #body="{ data }">
                                <div class="space-y-1">
                                    <div class="font-medium text-slate-800">{{ data.userName }}</div>
                                    <div class="text-xs text-slate-500">{{ data.userEmail }}</div>
                                </div>
                            </template>
                        </Column>

                        <Column field="isActive" header="Status" sortable>
                            <template #body="{ data }">
                                <Tag :value="data.isActive ? 'Active' : 'Inactive'" :severity="data.isActive ? 'success' : 'warn'" />
                            </template>
                        </Column>

                        <Column field="allowedFrom" header="Allowed From" sortable>
                            <template #body="{ data }">
                                <span class="text-sm text-slate-600">{{ formatDate(data.allowedFrom) }}</span>
                            </template>
                        </Column>

                        <Column field="allowedUntil" header="Allowed Until" sortable>
                            <template #body="{ data }">
                                <span class="text-sm text-slate-600">{{ formatDate(data.allowedUntil) }}</span>
                            </template>
                        </Column>

                        <Column field="notes" header="Notes">
                            <template #body="{ data }">
                                <div class="max-w-sm text-sm text-slate-600">
                                    {{ data.notes || 'No notes' }}
                                </div>
                            </template>
                        </Column>

                        <Column v-if="canManageClientAccess" header="Actions" :exportable="false" style="width: 5rem">
                            <template #body="{ data }">
                                <RowActionMenu :items="actionItems(data)" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <template #footer>
                    <div class="flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            Showing {{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} of {{ pagination.total }} access records
                        </div>
                        <div>Page {{ pagination.currentPage }} / {{ pagination.lastPage }}</div>
                    </div>
                </template>
            </AdminTableCard>
        </div>

        <CreateDialog
            v-model:visible="createVisible"
            :clientOptions="clientOptions"
            :userOptions="userOptions"
            @saved="handleSaved"
        />

        <EditDialog
            v-model:visible="editVisible"
            :access="selectedAccess"
            :clientOptions="clientOptions"
            :userOptions="userOptions"
            @saved="handleSaved"
            @update:visible="(value) => !value && closeEditDialog()"
        />
    </AuthenticatedLayout>
</template>
