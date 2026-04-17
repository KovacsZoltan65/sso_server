<script setup>
import AdminTableCard from '@/Components/Admin/AdminTableCard.vue';
import BaseDataTable from '@/Components/Admin/BaseDataTable.vue';
import AdminTableToolbar from '@/Components/Admin/AdminTableToolbar.vue';
import RowActionMenu from '@/Components/Admin/RowActionMenu.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { trans } from 'laravel-vue-i18n';
import { useAdminListActions } from '@/Composables/useAdminListActions';
import { useAdminTableSelection } from '@/Composables/useAdminTableSelection';
import { useAdminSearchBehavior } from '@/Composables/useAdminSearchBehavior';
import { Head, router } from '@inertiajs/vue3';
import { FilterMatchMode } from '@primevue/core/api';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import ConfirmDialog from 'primevue/confirmdialog';
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
            code: null,
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
            field: 'name',
            order: 1,
        }),
    },
    canManageScopes: {
        type: Boolean,
        default: false,
    },
});

const rows = computed(() => props.rows);
const searchBehavior = useAdminSearchBehavior();

const tableFilters = ref({
    global: { value: props.filters.global ?? null, matchMode: FilterMatchMode.CONTAINS },
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
    indexRouteName: 'admin.scopes.index',
    destroyRouteName: 'admin.scopes.destroy',
    bulkDestroyRouteName: 'admin.scopes.bulk-destroy',
    entityLabel: trans('pages.scopes.item'),
    entityLabelPlural: trans('pages.scopes.items'),
    buildParams,
    clearSelection,
    selectedIds,
    pageState: tableState,
    getCurrentRowCount: () => rows.value.length,
});

const onGlobalFilterInput = (value) => {
    tableFilters.value.global.value = value ?? null;
    searchBehavior.queueSearch(() => {
        tableState.page = 1;
        reload({
            page: 1,
            global: value || undefined,
        }, { resetSelection: true });
    });
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
    router.get(route('admin.scopes.create'));
};

const goToEditPage = (scope) => {
    router.get(route('admin.scopes.edit', scope.id));
};

const scopeActionItems = (scope) => [
    {
        label: trans('actions.edit'),
        icon: 'pi pi-pencil',
        isPrimary: true,
        command: () => goToEditPage(scope),
    },
    {
        label: trans('actions.delete'),
        icon: 'pi pi-trash',
        isDangerous: true,
        disabled: !scope.canDelete,
        command: () => confirmDelete(scope),
    },
];
</script>

<template>
    <Head :title="trans('navigation.scopes.label')" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                :title="trans('navigation.scopes.label')"
                :description="trans('navigation.scopes.description')"
            />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <BaseDataTable
                        :value="rows"
                        v-model:filters="tableFilters"
                        :rows="tableState.perPage"
                        :first="pagination.first"
                        :total-records="pagination.total"
                        :sort-field="tableState.sortField"
                        :sort-order="tableState.sortOrder"
                        :loading="busy"
                        :empty-message="trans('table.empty')"
                        :loading-message="trans('table.loading')"
                        data-key="id"
                        lazy
                        filterDisplay="menu"
                        removableSort
                        responsive-layout="scroll"
                        @sort="onSort"
                        @page="onPage"
                    >
                        <template #header>
                            <AdminTableToolbar
                                :canCreate="canManageScopes"
                                :createLabel="trans('actions.create')"
                                :canBulkDelete="canManageScopes"
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
                                            :placeholder="trans('pages.scopes.search_placeholder')"
                                            class="w-full"
                                            @update:modelValue="onGlobalFilterInput"
                                        />
                                    </IconField>
                                </template>
                            </AdminTableToolbar>
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
                        <Column field="description" :header="trans('table.columns.description')">
                            <template #body="{ data }">
                                <div class="max-w-xl text-sm text-slate-600">
                                    {{ data.description || trans('messages.no_description') }}
                                </div>
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

                        <Column v-if="canManageScopes" :header="trans('table.columns.actions')" :exportable="false" style="width: 12rem">
                            <template #body="{ data }">
                                <RowActionMenu :items="scopeActionItems(data)" />
                            </template>
                        </Column>
                    </BaseDataTable>
                </div>

                <template #footer>
                    <div class="flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            {{ trans('table.showing_of', { from: pagination.from ?? 0, to: pagination.to ?? 0, total: pagination.total, item: trans('pages.scopes.items') }) }}
                        </div>
                        <div>{{ trans('table.page_of', { current: pagination.currentPage, last: pagination.lastPage }) }}</div>
                    </div>
                </template>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
