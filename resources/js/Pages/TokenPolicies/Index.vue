<script setup>
import AdminTableCard from '@/Components/Admin/AdminTableCard.vue';
import AdminTableToolbar from '@/Components/Admin/AdminTableToolbar.vue';
import RowActionMenu from '@/Components/Admin/RowActionMenu.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useAdminListActions } from '@/Composables/useAdminListActions';
import { useAdminTableSelection } from '@/Composables/useAdminTableSelection';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { trans } from 'laravel-vue-i18n';
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
    entityLabel: trans('pages.token_policies.item'),
    entityLabelPlural: trans('pages.token_policies.items'),
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
    { label: trans('actions.edit'), icon: 'pi pi-pencil', isPrimary: true, command: () => goToEditPage(tokenPolicy) },
    { label: trans('actions.delete'), icon: 'pi pi-trash', isDangerous: true, disabled: !tokenPolicy.canDelete, command: () => confirmDelete(tokenPolicy) },
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
    <Head :title="trans('navigation.token_policies.label')" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                :title="trans('navigation.token_policies.label')"
                :description="trans('navigation.token_policies.description')"
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
                                :createLabel="trans('actions.create')"
                                :canBulkDelete="canManageTokenPolicies"
                                :bulkDeleteLabel="trans('toolbar.bulk.delete')"
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
                                            :placeholder="trans('pages.token_policies.search_placeholder')"
                                            class="w-full"
                                            @update:modelValue="onGlobalFilterInput"
                                        />
                                    </IconField>
                                </template>
                            </AdminTableToolbar>
                        </template>

                        <template #empty>
                            <div class="py-8 text-center text-sm text-slate-500">
                                {{ trans('table.empty') }}
                            </div>
                        </template>

                        <Column headerStyle="width: 3.5rem" bodyStyle="width: 3.5rem">
                            <template #header>
                                <div :title="selectableRows.length === 0 ? trans('toolbar.bulk.none') : ''">
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

                        <Column field="name" :header="trans('table.columns.name')" sortable />
                        <Column field="code" :header="trans('table.columns.code')" sortable>
                            <template #body="{ data }">
                                <code class="rounded bg-slate-100 px-2 py-1 text-sm text-slate-700">{{ data.code }}</code>
                            </template>
                        </Column>
                        <Column field="accessTokenTtlMinutes" :header="trans('table.columns.access_ttl')" sortable>
                            <template #body="{ data }">{{ formatMinutes(data.accessTokenTtlMinutes) }}</template>
                        </Column>
                        <Column field="refreshTokenTtlMinutes" :header="trans('table.columns.refresh_ttl')" sortable>
                            <template #body="{ data }">{{ formatMinutes(data.refreshTokenTtlMinutes) }}</template>
                        </Column>
                        <Column field="refreshTokenRotationEnabled" :header="trans('table.columns.rotation')">
                            <template #body="{ data }">
                                <Tag :value="data.refreshTokenRotationEnabled ? trans('status.enabled') : trans('status.disabled')" :severity="data.refreshTokenRotationEnabled ? 'success' : 'secondary'" />
                            </template>
                        </Column>
                        <Column field="pkceRequired" :header="trans('table.columns.pkce')">
                            <template #body="{ data }">
                                <Tag :value="data.pkceRequired ? trans('status.required') : trans('status.optional')" :severity="data.pkceRequired ? 'info' : 'secondary'" />
                            </template>
                        </Column>
                        <Column field="isDefault" :header="trans('table.columns.default')">
                            <template #body="{ data }">
                                <Tag :value="data.isDefault ? trans('status.default') : trans('status.custom')" :severity="data.isDefault ? 'warn' : 'secondary'" />
                            </template>
                        </Column>
                        <Column field="isActive" :header="trans('table.columns.status')">
                            <template #body="{ data }">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Tag :value="data.isActive ? trans('status.active') : trans('status.inactive')" :severity="data.isActive ? 'success' : 'warn'" />
                                    <Tag v-if="data.deleteBlockCode === 'assigned_clients'" :value="trans('status.in_use')" severity="info" />
                                </div>
                            </template>
                        </Column>
                        <Column field="createdAt" :header="trans('table.columns.created_at')" sortable />

                        <Column v-if="canManageTokenPolicies" :header="trans('table.columns.actions')" :exportable="false" style="width: 12rem">
                            <template #body="{ data }">
                                <RowActionMenu :items="tokenPolicyActionItems(data)" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <template #footer>
                    <div class="flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            {{ trans('table.showing_of', { from: pagination.from ?? 0, to: pagination.to ?? 0, total: pagination.total, item: trans('pages.token_policies.items') }) }}
                        </div>
                        <div>{{ trans('table.page_of', { current: pagination.currentPage, last: pagination.lastPage }) }}</div>
                    </div>
                </template>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
