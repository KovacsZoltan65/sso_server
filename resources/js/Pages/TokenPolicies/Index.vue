<script setup>
import AdminTableCard from '@/Components/Admin/AdminTableCard.vue';
import AdminTableToolbar from '@/Components/Admin/AdminTableToolbar.vue';
import RowActionMenu from '@/Components/Admin/RowActionMenu.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useAdminListActions } from '@/Composables/useAdminListActions';
import { useAdminTableSelection } from '@/Composables/useAdminTableSelection';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
    rows: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({ global: null, status: null }),
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
        default: () => ({ field: 'name', order: 1 }),
    },
    canManageTokenPolicies: {
        type: Boolean,
        default: false,
    },
});

const rows = computed(() => props.rows);
const tableFilters = ref({
    global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
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
    page: tableState.page,
    perPage: tableState.perPage,
    sortField: tableState.sortField || undefined,
    sortOrder: tableState.sortOrder || undefined,
    ...overrides,
});

const { busy, reload, refresh, confirmDelete, confirmBulkDelete } = useAdminListActions({
    indexRouteName: 'admin.token-policies.index',
    destroyRouteName: 'admin.token-policies.destroy',
    bulkDestroyRouteName: 'admin.token-policies.bulk-destroy',
    entityLabel: 'Token Policy',
    entityLabelPlural: 'token policies',
    buildParams,
    clearSelection,
    selectedIds,
    pageState: tableState,
    getCurrentRowCount: () => rows.value.length,
});

const onGlobalFilterInput = (value) => {
    tableFilters.value.global.value = value ?? null;
    tableState.page = 1;
    reload({ page: 1, global: value || undefined }, { resetSelection: true });
};

const onSort = (event) => {
    tableState.sortField = event.sortField;
    tableState.sortOrder = event.sortOrder;
    reload({ sortField: event.sortField, sortOrder: event.sortOrder }, { resetSelection: true });
};

const onPage = (event) => {
    tableState.page = event.page + 1;
    tableState.perPage = event.rows;
    reload({ page: event.page + 1, perPage: event.rows }, { resetSelection: true });
};

const goToCreatePage = () => router.get(route('admin.token-policies.create'));
const goToEditPage = (tokenPolicy) => router.get(route('admin.token-policies.edit', tokenPolicy.id));

const tokenPolicyActionItems = (tokenPolicy) => [
    { label: 'Edit', icon: 'pi pi-pencil', command: () => goToEditPage(tokenPolicy) },
    { label: 'Delete', icon: 'pi pi-trash', disabled: !tokenPolicy.canDelete, command: () => confirmDelete(tokenPolicy) },
];

const formatMinutes = (minutes) => {
    if (minutes >= 1440 && minutes % 1440 === 0) {
        return `${minutes / 1440}d`;
    }

    if (minutes >= 60 && minutes % 60 === 0) {
        return `${minutes / 60}h`;
    }

    return `${minutes}m`;
};
</script>

<template>
    <Head title="Token Policies" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                title="Token Policies"
                description="Manage access and refresh token issuance rules, PKCE requirements, rotation, and default policy behavior."
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
                        striped-rows
                        filterDisplay="menu"
                        removableSort
                        responsive-layout="scroll"
                        @sort="onSort"
                        @page="onPage"
                    >
                        <template #header>
                            <AdminTableToolbar
                                :canCreate="canManageTokenPolicies"
                                createLabel="Create Token Policy"
                                :canBulkDelete="canManageTokenPolicies"
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
                                            placeholder="Search token policies"
                                            class="w-full"
                                            @update:modelValue="onGlobalFilterInput"
                                        />
                                    </IconField>
                                </template>
                            </AdminTableToolbar>
                        </template>

                        <template #empty>
                            <div class="py-8 text-center text-sm text-slate-500">
                                No token policies found for the current filters.
                            </div>
                        </template>

                        <Column headerStyle="width: 3.5rem" bodyStyle="width: 3.5rem">
                            <template #header>
                                <div :title="selectableRows.length === 0 ? 'No deletable token policies on this page.' : ''">
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

                        <Column field="name" header="Name" sortable />
                        <Column field="code" header="Code" sortable>
                            <template #body="{ data }">
                                <code class="rounded bg-slate-100 px-2 py-1 text-sm text-slate-700">{{ data.code }}</code>
                            </template>
                        </Column>
                        <Column field="accessTokenTtlMinutes" header="Access TTL" sortable>
                            <template #body="{ data }">{{ formatMinutes(data.accessTokenTtlMinutes) }}</template>
                        </Column>
                        <Column field="refreshTokenTtlMinutes" header="Refresh TTL" sortable>
                            <template #body="{ data }">{{ formatMinutes(data.refreshTokenTtlMinutes) }}</template>
                        </Column>
                        <Column field="refreshTokenRotationEnabled" header="Rotation">
                            <template #body="{ data }">
                                <Tag :value="data.refreshTokenRotationEnabled ? 'Enabled' : 'Disabled'" :severity="data.refreshTokenRotationEnabled ? 'success' : 'secondary'" />
                            </template>
                        </Column>
                        <Column field="pkceRequired" header="PKCE">
                            <template #body="{ data }">
                                <Tag :value="data.pkceRequired ? 'Required' : 'Optional'" :severity="data.pkceRequired ? 'info' : 'secondary'" />
                            </template>
                        </Column>
                        <Column field="isDefault" header="Default">
                            <template #body="{ data }">
                                <Tag :value="data.isDefault ? 'Default' : 'Custom'" :severity="data.isDefault ? 'warn' : 'secondary'" />
                            </template>
                        </Column>
                        <Column field="isActive" header="Status">
                            <template #body="{ data }">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Tag :value="data.isActive ? 'Active' : 'Inactive'" :severity="data.isActive ? 'success' : 'warn'" />
                                    <Tag v-if="data.deleteBlockCode === 'assigned_clients'" value="In Use" severity="info" />
                                </div>
                            </template>
                        </Column>
                        <Column field="createdAt" header="Created At" sortable />

                        <Column v-if="canManageTokenPolicies" header="Actions" :exportable="false" style="width: 5rem">
                            <template #body="{ data }">
                                <RowActionMenu :items="tokenPolicyActionItems(data)" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <template #footer>
                    <div class="flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            Showing {{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} of {{ pagination.total }} token policies
                        </div>
                        <div>Page {{ pagination.currentPage }} / {{ pagination.lastPage }}</div>
                    </div>
                </template>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
