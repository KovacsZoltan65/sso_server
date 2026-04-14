<script setup>
import AdminTableCard from '@/Components/Admin/AdminTableCard.vue';
import BaseDataTable from '@/Components/Admin/BaseDataTable.vue';
import AdminTableSummary from '@/Components/Admin/AdminTableSummary.vue';
import AdminTableToolbar from '@/Components/Admin/AdminTableToolbar.vue';
import RowActionMenu from '@/Components/Admin/RowActionMenu.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAdminListActions } from '@/Composables/useAdminListActions';
import { useAdminTableState } from '@/Composables/useAdminTableState';
import { useAdminTableSelection } from '@/Composables/useAdminTableSelection';
import { Head, router } from '@inertiajs/vue3';
import { FilterMatchMode } from '@primevue/core/api';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import ConfirmDialog from 'primevue/confirmdialog';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { computed } from 'vue';

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
    initialSortField: props.sorting.field ?? 'createdAt',
    initialSortOrder: props.sorting.order ?? -1,
    initialTotalRecords: props.pagination.total ?? 0,
    initialFilters: {
        global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
        client_id: { value: props.filters.client_id ?? null, matchMode: FilterMatchMode.EQUALS },
        user_id: { value: props.filters.user_id ?? null, matchMode: FilterMatchMode.EQUALS },
        status: { value: props.filters.status ?? null, matchMode: FilterMatchMode.EQUALS },
    },
    serializeSortOrder: (value) => value,
});

const statusOptions = [
    { label: 'All statuses', value: null },
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

const clientSelectOptions = computed(() => [
    { label: 'All clients', value: null },
    ...props.clientOptions.map((client) => ({
        label: `${client.name} (${client.clientId})`,
        value: client.id,
    })),
]);

const userSelectOptions = computed(() => [
    { label: 'All users', value: null },
    ...props.userOptions.map((user) => ({
        label: `${user.name} (${user.email})`,
        value: user.id,
    })),
]);

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
        client_id: tableFilters.client_id.value || undefined,
        user_id: tableFilters.user_id.value || undefined,
        status: tableFilters.status.value || undefined,
    },
    extra: overrides,
});

const {
    busy,
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
    tableFilters.global.value = value ?? null;
    resetPagination();

    reload(buildParams({
        page: 1,
        global: value || undefined,
    }), { resetSelection: true });
};

const onSort = (event) => {
    setSortFromEvent(event, 'createdAt');
    reload(buildParams(), { resetSelection: true });
};

const onPage = (event) => {
    setPageFromEvent(event);
    reload(buildParams(), { resetSelection: true });
};

const onSelectFilterChange = () => {
    resetPagination();

    reload(buildParams({
        page: 1,
        client_id: tableFilters.client_id.value || undefined,
        user_id: tableFilters.user_id.value || undefined,
        status: tableFilters.status.value || undefined,
    }), { resetSelection: true });
};

const goToCreatePage = () => {
    router.get(route('admin.client-user-access.create'));
};

const goToEditPage = (access) => {
    router.get(route('admin.client-user-access.edit', access.id));
};

const actionItems = (access) => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        isPrimary: true,
        command: () => goToEditPage(access),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        isDangerous: true,
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
                    <div class="mx-6 mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-client-access-rule>
                        <div class="font-medium">Access rule</div>
                        <div class="mt-1">
                            If a client has no active client access records, it behaves as an open client and any active authenticated user may authorize.
                            Once at least one active access record exists, the client becomes restricted and only explicitly assigned active users may authorize.
                        </div>
                    </div>

                    <div class="grid gap-3 px-6 md:grid-cols-3 xl:grid-cols-4">
                        <Select
                            v-model="tableFilters.client_id.value"
                            :options="clientSelectOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Client"
                            class="w-full"
                            @change="onSelectFilterChange"
                        />
                        <Select
                            v-model="tableFilters.user_id.value"
                            :options="userSelectOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="User"
                            class="w-full"
                            @change="onSelectFilterChange"
                        />
                        <Select
                            v-model="tableFilters.status.value"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Status"
                            class="w-full"
                            @change="onSelectFilterChange"
                        />
                    </div>

                    <BaseDataTable
                        :value="rows"
                        v-model:filters="tableFilters"
                        :rows="tableState.perPage"
                        :first="first"
                        :total-records="pagination.total"
                        :sort-field="tableState.sortField"
                        :sort-order="tableState.sortOrder"
                        :loading="busy"
                        empty-message="No client access records found for the current filters."
                        loading-message="Loading client access records..."
                        data-key="id"
                        lazy
                        responsive-layout="scroll"
                        @sort="onSort"
                        @page="onPage"
                    >
                        <template #header>
                            <AdminTableToolbar
                                searchable
                                :search-value="tableFilters.global.value ?? ''"
                                search-placeholder="Search access"
                                :canCreate="canManageClientAccess"
                                createLabel="Create Access"
                                :canBulkDelete="canManageClientAccess"
                                bulkDeleteLabel="Delete Selected"
                                :selectedCount="selectedCount"
                                :selectableCount="selectableCount"
                                :busy="busy"
                                @update:searchValue="onGlobalFilterInput"
                                @create="goToCreatePage"
                                @bulk-delete="confirmBulkDelete"
                                @refresh="refresh"
                            />
                        </template>

                        <Column headerStyle="width: 3.5rem" bodyStyle="width: 3.5rem">
                            <template #header>
                                <div :title="selectableCount === 0 ? 'No deletable access records on this page.' : ''">
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

                        <Column v-if="canManageClientAccess" header="Actions" :exportable="false" style="width: 12rem">
                            <template #body="{ data }">
                                <RowActionMenu :items="actionItems(data)" />
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
                        item-label="access records"
                    />
                </template>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
