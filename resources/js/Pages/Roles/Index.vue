<script setup>
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import PageHeader from "@/Components/PageHeader.vue";
import { trans } from "laravel-vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useAdminListActions } from "@/Composables/useAdminListActions";
import { useAdminTableSelection } from "@/Composables/useAdminTableSelection";
import { Head, router } from "@inertiajs/vue3";
import { FilterMatchMode } from "@primevue/core/api";
import Checkbox from "primevue/checkbox";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { computed, reactive, ref } from "vue";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";

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
    sortField: props.sorting.field ?? "name",
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

const { busy, reload, refresh, confirmDelete, confirmBulkDelete } = useAdminListActions({
    indexRouteName: "admin.roles.index",
    destroyRouteName: "admin.roles.destroy",
    bulkDestroyRouteName: "admin.roles.bulk-destroy",
    entityLabel: trans("pages.roles.item"),
    entityLabelPlural: trans("pages.roles.items"),
    buildParams,
    clearSelection,
    selectedIds,
    pageState: tableState,
    getCurrentRowCount: () => rows.value.length,
});

const onGlobalFilterInput = (value) => {
    tableFilters.value.global.value = value ?? null;
    tableState.page = 1;
    reload(
        {
            page: 1,
            global: value || undefined,
        },
        { resetSelection: true }
    );
};

const onFilter = (event) => {
    tableState.page = 1;

    reload(
        {
            page: 1,
            global: event.filters.global?.value || undefined,
            name: event.filters.name?.value || undefined,
        },
        { resetSelection: true }
    );
};

const onSort = (event) => {
    tableState.sortField = event.sortField;
    tableState.sortOrder = event.sortOrder;

    reload(
        {
            sortField: event.sortField,
            sortOrder: event.sortOrder,
        },
        { resetSelection: true }
    );
};

const onPage = (event) => {
    tableState.page = event.page + 1;
    tableState.perPage = event.rows;

    reload(
        {
            page: event.page + 1,
            perPage: event.rows,
        },
        { resetSelection: true }
    );
};

const goToCreatePage = () => {
    router.get(route("admin.roles.create"));
};

const goToEditPage = (role) => {
    router.get(route("admin.roles.edit", role.id));
};

const roleActionItems = (role) => [
    {
        label: trans("actions.edit"),
        icon: "pi pi-pencil",
        isPrimary: true,
        command: () => goToEditPage(role),
    },
    {
        label: trans("actions.delete"),
        icon: "pi pi-trash",
        isDangerous: true,
        disabled: !role.canDelete,
        command: () => confirmDelete(role),
    },
];
</script>

<template>
    <Head :title="trans('navigation.roles.label')" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                :title="trans('navigation.roles.label')"
                :description="trans('navigation.roles.description')"
            />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <BaseDataTable
                        :value="rows"
                        :loading="busy"
                        :loading-message="trans('')"
                        :empty-message="trans('')"
                        removable-sort
                        data-key="id"
                        :rows="tableState.perPage"
                        :first="pagination.first"
                        :total-records="pagination.total"
                        :sort-field="tableState.sortField"
                        :sort-order="tableState.sortOrder"
                        :rows-per-page-options="perPageOptions"
                        @page="onFilter"
                        @sort="onSort"
                    >
                        <template #header>
                            <AdminTableToolbar
                                :canCreate="canManageRoles"
                                :createLabel="trans('actions.create')"
                                :canBulkDelete="canManageRoles"
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
                                            :placeholder="
                                                trans('roles.search_placeholder')
                                            "
                                            class="w-full"
                                            @update:modelValue="onGlobalFilterInput"
                                        />
                                    </IconField>
                                </template>
                            </AdminTableToolbar>
                        </template>

                        <template #empty>
                            <div class="py-8 text-center text-sm text-slate-500">
                                {{ trans("table.empty") }}
                            </div>
                        </template>

                        <!-- Selector -->
                        <Column selectionMode="multiple" headerStyle="width: 3rem" />

                        <!-- Name -->
                        <Column
                            field="name"
                            :header="trans('table.columns.name')"
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
                                        :value="trans('status.protected')"
                                        severity="warn"
                                    />
                                </div>
                            </template>

                            <template #filter="{ filterModel, filterCallback }">
                                <InputText
                                    v-model="filterModel.value"
                                    :placeholder="trans('table.filter_name')"
                                    class="w-full"
                                    @input="filterCallback()"
                                />
                            </template>
                        </Column>

                        <!-- Guard -->
                        <Column field="guardName" :header="trans('table.columns.guard')">
                            <template #body="{ data }">
                                <Tag :value="data.guardName" severity="secondary" />
                            </template>
                        </Column>

                        <!-- Permissions -->
                        <Column :header="trans('table.columns.permissions')">
                            <template #body="{ data }">
                                <div
                                    v-if="data.permissions?.length"
                                    class="flex flex-wrap gap-2"
                                >
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
                                    {{ trans("messages.no_permissions") }}
                                </span>
                            </template>
                        </Column>

                        <!-- Assigned User -->
                        <Column
                            field="usersCount"
                            :header="trans('table.columns.assigned_users')"
                        />

                        <!-- Created At -->
                        <Column
                            field="createdAt"
                            :header="trans('table.columns.created_at')"
                            sortable
                        />

                        <!-- Actions -->
                        <Column
                            v-if="canManageRoles"
                            :header="trans('common.actions')"
                            :exportable="false"
                            style="width: 12rem"
                        >
                            <template #body="{ data }">
                                <RowActionMenu :items="roleActionItems(data)" />
                            </template>
                        </Column>
                    </BaseDataTable>
                </div>

                <template #footer>
                    <div
                        class="flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            {{
                                trans("table.showing_of", {
                                    from: pagination.from ?? 0,
                                    to: pagination.to ?? 0,
                                    total: pagination.total,
                                    item: trans("pages.roles.items"),
                                })
                            }}
                        </div>
                        <div>
                            {{
                                trans("table.page_of", {
                                    current: pagination.currentPage,
                                    last: pagination.lastPage,
                                })
                            }}
                        </div>
                    </div>
                </template>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
